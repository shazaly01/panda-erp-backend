<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Services\Reporting;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Enums\EntryStatus;
use App\Modules\Accounting\Enums\AccountType;
use Illuminate\Support\Facades\DB;

class IncomeStatementService
{
    /**
     * استخراج قائمة الدخل (الأرباح والخسائر) لفترة محددة
     */
    public function getIncomeStatement(string $fromDate, string $toDate, bool $includeDrafts = false): array
    {
        $statuses = $includeDrafts
            ? [EntryStatus::Posted->value, EntryStatus::Draft->value]
            : [EntryStatus::Posted->value];

        // 1. جلب الحركات (فقط خلال الفترة المحددة، لا يوجد رصيد افتتاحي في قائمة الدخل)
        $balances = DB::table('journal_entry_details as jed')
            ->join('journal_entries as je', 'je.id', '=', 'jed.journal_entry_id')
            ->whereIn('je.status', $statuses)
            ->whereBetween('je.date', [$fromDate, $toDate])
            ->whereNull('je.deleted_at')
            ->selectRaw('jed.account_id, SUM(jed.debit) as total_debit, SUM(jed.credit) as total_credit')
            ->groupBy('jed.account_id')
            ->get()
            ->keyBy('account_id');

        // 2. جلب حسابات الإيرادات والمصروفات فقط، مرتبة شجرياً
        $accounts = Account::whereIn('type', [AccountType::REVENUE->value, AccountType::EXPENSE->value])
            ->defaultOrder()
            ->get();

        $revenuesData = [];
        $expensesData = [];

        $grandTotalRevenues = 0;
        $grandTotalExpenses = 0;
        $accountTotals = [];

        // 3. بناء الشجرة من الأسفل للأعلى لضمان التجميع
        foreach ($accounts->reverse() as $account) {
            $debit = 0;
            $credit = 0;

            if ($account->is_transactional) {
                // استخراج رصيد الحساب الفرعي
                $balance = $balances->get($account->id);
                $debit = $balance ? (float) $balance->total_debit : 0;
                $credit = $balance ? (float) $balance->total_credit : 0;

                // تجميع الإجماليات الكبرى من الحسابات الفرعية فقط لمنع التكرار
                if ($account->type->value === AccountType::REVENUE->value) {
                    $grandTotalRevenues += ($credit - $debit);
                } else {
                    $grandTotalExpenses += ($debit - $credit);
                }
            } else {
                // إذا كان رئيسياً، يجمع أرصدة أبنائه
                foreach ($account->children as $child) {
                    $debit += $accountTotals[$child->id]['debit'] ?? 0;
                    $credit += $accountTotals[$child->id]['credit'] ?? 0;
                }
            }

            // حفظ المجموع للآباء
            $accountTotals[$account->id] = [
                'debit' => $debit,
                'credit' => $credit,
            ];

            // حساب الرصيد الصافي للظهور في التقرير
            $netBalance = $account->type->value === AccountType::REVENUE->value
                ? ($credit - $debit)
                : ($debit - $credit);

            // إخفاء الحسابات الصفرية
            if ($debit == 0 && $credit == 0 && $netBalance == 0) {
                continue;
            }

            $node = [
                'id'               => $account->id,
                'parent_id'        => $account->parent_id,
                'code'             => $account->code,
                'name'             => $account->name,
                'is_transactional' => $account->is_transactional,
                'balance'          => $netBalance
            ];

            // فصل الإيرادات عن المصروفات
            if ($account->type->value === AccountType::REVENUE->value) {
                $revenuesData[] = $node;
            } else {
                $expensesData[] = $node;
            }
        }

        return [
            'period' => [
                'from_date' => $fromDate,
                'to_date'   => $toDate,
            ],
            'summary' => [
                'total_revenues' => $grandTotalRevenues,
                'total_expenses' => $grandTotalExpenses,
                'net_profit'     => $grandTotalRevenues - $grandTotalExpenses, // النتيجة النهائية
            ],
            // نعكس المصفوفة ليعود الترتيب الشجري الصحيح من الآباء للأبناء
            'revenues' => array_reverse($revenuesData),
            'expenses' => array_reverse($expensesData),
        ];
    }
}
