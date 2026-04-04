<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Services\Reporting;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Enums\EntryStatus;
use Illuminate\Support\Facades\DB;

class TrialBalanceService
{
    /**
     * استخراج ميزان المراجعة حتى تاريخ معين
     */
    public function getTrialBalance(string $asOfDate, bool $includeDrafts = false): array
    {
        $statuses = $includeDrafts
            ? [EntryStatus::Posted->value, EntryStatus::Draft->value]
            : [EntryStatus::Posted->value];

        // 1. جلب مجاميع المدين والدائن لكل حساب فرعي (Transaction Accounts) من قاعدة البيانات مباشرة
        $balances = DB::table('journal_entry_details as jed')
            ->join('journal_entries as je', 'je.id', '=', 'jed.journal_entry_id')
            ->whereIn('je.status', $statuses)
            ->where('je.date', '<=', $asOfDate)
            ->whereNull('je.deleted_at') // تجاهل المحذوف
            ->selectRaw('jed.account_id, SUM(jed.debit) as total_debit, SUM(jed.credit) as total_credit')
            ->groupBy('jed.account_id')
            ->get()
            ->keyBy('account_id');

        // 2. جلب شجرة الحسابات بالكامل مرتبة
        $accounts = Account::defaultOrder()->get();

        $reportData = [];
        $grandTotalDebit = 0;
        $grandTotalCredit = 0;

        // 3. بناء هيكل ميزان المراجعة (من الأسفل للأعلى لضمان التجميع الصحيح)
        // نستخدم reverse() لنبدأ من الأبناء ثم الآباء
        $accountTotals = [];

        foreach ($accounts->reverse() as $account) {
            $debit = 0;
            $credit = 0;

            if ($account->is_transactional) {
                // إذا كان حساباً فرعياً، نأخذ رصيده من الاستعلام
                $balance = $balances->get($account->id);
                $debit = $balance ? (float) $balance->total_debit : 0;
                $credit = $balance ? (float) $balance->total_credit : 0;

                // إضافة إجمالي الحسابات الفرعية للمجاميع الكبرى للميزان
                $grandTotalDebit += $debit;
                $grandTotalCredit += $credit;
            } else {
                // إذا كان حساباً رئيسياً، نجمع أرصدة أبنائه المباشرين
                foreach ($account->children as $child) {
                    $debit += $accountTotals[$child->id]['debit'] ?? 0;
                    $credit += $accountTotals[$child->id]['credit'] ?? 0;
                }
            }

            // حفظ المجموع لاستخدامه للآباء في الدورات القادمة
            $accountTotals[$account->id] = [
                'debit' => $debit,
                'credit' => $credit,
            ];

            // لا نظهر الحسابات الصفرية في التقرير لتنظيف العرض (اختياري، يمكنك إلغاؤه)
            if ($debit == 0 && $credit == 0) {
                continue;
            }

            // إضافة الحساب للتقرير
            $reportData[] = [
                'id'               => $account->id,
                'parent_id'        => $account->parent_id,
                'code'             => $account->code,
                'name'             => $account->name,
                'type'             => $account->type->label(),
                'nature'           => $account->nature->value,
                'is_transactional' => $account->is_transactional,
                'debit'            => $debit,
                'credit'           => $credit,
                'balance'          => $account->nature->value === 'debit' ? ($debit - $credit) : ($credit - $debit)
            ];
        }

        // بما أننا جمعنا من الأسفل للأعلى، نعكس المصفوفة لتعود للترتيب الشجري الطبيعي
        $reportData = array_reverse($reportData);

        return [
            'as_of_date' => $asOfDate,
            'summary' => [
                'total_debit'  => $grandTotalDebit,
                'total_credit' => $grandTotalCredit,
                'is_balanced'  => round($grandTotalDebit, 2) === round($grandTotalCredit, 2),
            ],
            'accounts' => $reportData
        ];
    }
}
