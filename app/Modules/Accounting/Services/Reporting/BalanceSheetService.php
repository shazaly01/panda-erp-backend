<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Services\Reporting;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Enums\EntryStatus;
use App\Modules\Accounting\Enums\AccountType;
use Illuminate\Support\Facades\DB;

class BalanceSheetService
{
    /**
     * استخراج الميزانية العمومية (المركز المالي) حتى تاريخ محدد
     */
    public function getBalanceSheet(string $asOfDate, bool $includeDrafts = false): array
    {
        $statuses = $includeDrafts
            ? [EntryStatus::Posted->value, EntryStatus::Draft->value]
            : [EntryStatus::Posted->value];

        // 1. جلب الأرصدة التراكمية لكل الحسابات حتى تاريخ الميزانية (التاريخ المعطى)
        $balances = DB::table('journal_entry_details as jed')
            ->join('journal_entries as je', 'je.id', '=', 'jed.journal_entry_id')
            ->whereIn('je.status', $statuses)
            ->where('je.date', '<=', $asOfDate) // تراكمي منذ بداية العمل
            ->whereNull('je.deleted_at')
            ->selectRaw('jed.account_id, SUM(jed.debit) as total_debit, SUM(jed.credit) as total_credit')
            ->groupBy('jed.account_id')
            ->get()
            ->keyBy('account_id');

        // 2. جلب شجرة الحسابات بالكامل
        $accounts = Account::defaultOrder()->get();

        $assetsData = [];
        $liabilitiesData = [];
        $equityData = [];

        $totalAssets = 0;
        $totalLiabilities = 0;
        $totalEquity = 0;
        $netProfit = 0; // لجمع صافي الربح من الإيرادات والمصروفات

        $accountTotals = [];

        // 3. بناء الشجرة من الأسفل للأعلى
        foreach ($accounts->reverse() as $account) {
            $debit = 0;
            $credit = 0;

            if ($account->is_transactional) {
                // جلب رصيد الحساب الفرعي
                $balance = $balances->get($account->id);
                $debit = $balance ? (float) $balance->total_debit : 0;
                $credit = $balance ? (float) $balance->total_credit : 0;

                // التجميع حسب نوع الحساب
                if ($account->type->value === AccountType::ASSET->value) {
                    $totalAssets += ($debit - $credit); // الأصول مدينة
                } elseif ($account->type->value === AccountType::LIABILITY->value) {
                    $totalLiabilities += ($credit - $debit); // الخصوم دائنة
                } elseif ($account->type->value === AccountType::EQUITY->value) {
                    $totalEquity += ($credit - $debit); // حقوق الملكية دائنة
                } elseif ($account->type->value === AccountType::REVENUE->value) {
                    $netProfit += ($credit - $debit); // الإيرادات دائنة (تزيد الربح)
                } elseif ($account->type->value === AccountType::EXPENSE->value) {
                    $netProfit -= ($debit - $credit); // المصروفات مدينة (تنقص الربح)
                }
            } else {
                // تجميع أرصدة الأبناء للآباء
                foreach ($account->children as $child) {
                    $debit += $accountTotals[$child->id]['debit'] ?? 0;
                    $credit += $accountTotals[$child->id]['credit'] ?? 0;
                }
            }

            // حفظ المجموع
            $accountTotals[$account->id] = [
                'debit' => $debit,
                'credit' => $credit,
            ];

            // حساب الرصيد الصافي للعرض
            if (in_array($account->type->value, [AccountType::LIABILITY->value, AccountType::EQUITY->value, AccountType::REVENUE->value])) {
                $netBalance = $credit - $debit;
            } else {
                $netBalance = $debit - $credit;
            }

            // إضافة الأصول والخصوم وحقوق الملكية فقط للمصفوفات المخصصة للعرض
            if (in_array($account->type->value, [AccountType::ASSET->value, AccountType::LIABILITY->value, AccountType::EQUITY->value])) {
                if ($debit == 0 && $credit == 0 && $netBalance == 0) {
                    continue; // إخفاء الحسابات الصفرية
                }

                $node = [
                    'id'               => $account->id,
                    'parent_id'        => $account->parent_id,
                    'code'             => $account->code,
                    'name'             => $account->name,
                    'is_transactional' => $account->is_transactional,
                    'balance'          => $netBalance
                ];

                if ($account->type->value === AccountType::ASSET->value) {
                    $assetsData[] = $node;
                } elseif ($account->type->value === AccountType::LIABILITY->value) {
                    $liabilitiesData[] = $node;
                } elseif ($account->type->value === AccountType::EQUITY->value) {
                    $equityData[] = $node;
                }
            }
        }

        // 4. الحسابات الختامية للميزانية
        $totalEquityWithProfit = $totalEquity + $netProfit;
        $totalLiabilitiesAndEquity = $totalLiabilities + $totalEquityWithProfit;

        return [
            'as_of_date' => $asOfDate,
            'summary' => [
                'total_assets'                 => $totalAssets,
                'total_liabilities'            => $totalLiabilities,
                'total_equity'                 => $totalEquity,
                'net_profit'                   => $netProfit, // سيتم عرضه كبند مستقل تحت حقوق الملكية في الواجهة
                'total_equity_with_profit'     => $totalEquityWithProfit,
                'total_liabilities_and_equity' => $totalLiabilitiesAndEquity,
                'is_balanced'                  => round($totalAssets, 2) === round($totalLiabilitiesAndEquity, 2), // التأكد من توازن الميزانية
            ],
            // عكس المصفوفات لتعود للترتيب الطبيعي
            'assets'      => array_reverse($assetsData),
            'liabilities' => array_reverse($liabilitiesData),
            'equity'      => array_reverse($equityData),
        ];
    }
}
