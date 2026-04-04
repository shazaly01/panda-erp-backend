<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Services\Reporting;

use App\Modules\Accounting\Models\JournalEntryDetail;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Enums\EntryStatus;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AccountingReportingService
{
    /**
     * تقرير 1: كشف حساب تفصيلي (Ledger)
     * يعيد: الرصيد الافتتاحي + قائمة الحركات + الرصيد التراكمي
     */
    public function getAccountLedger(int $accountId, string $startDate, string $endDate): array
    {
        // 1. حساب الرصيد الافتتاحي (ما قبل تاريخ البداية)
        // الرصيد = مجموع المدين - مجموع الدائن (للحسابات المدينة)
        // ملاحظة: المعادلة قد تختلف حسب طبيعة الحساب، لكن محاسبياً نجمع الطرفين
        $openingBalance = JournalEntryDetail::query()
            ->where('account_id', $accountId)
            ->whereHas('entry', function ($q) use ($startDate) {
                $q->where('date', '<', $startDate)
                  ->where('status', EntryStatus::Posted); // فقط القيود المرحلة
            })
            ->selectRaw('SUM(debit) - SUM(credit) as balance')
            ->value('balance') ?? 0;

        // 2. جلب الحركات خلال الفترة
        $transactions = JournalEntryDetail::query()
            ->with(['entry', 'costCenter']) // نحتاج بيانات القيد
            ->where('account_id', $accountId)
            ->whereHas('entry', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('date', [$startDate, $endDate])
                  ->where('status', EntryStatus::Posted);
            })
            ->get();

        // 3. تنسيق البيانات وإضافة الرصيد التراكمي (Running Balance)
        $formattedTransactions = [];
        $runningBalance = (float) $openingBalance;

        foreach ($transactions as $trans) {
            $debit = (float) $trans->debit;
            $credit = (float) $trans->credit;
            $net = $debit - $credit;

            $runningBalance += $net;

            $formattedTransactions[] = [
                'date' => $trans->entry->date->format('Y-m-d'),
                'entry_number' => $trans->entry->entry_number,
                'description' => $trans->description ?? $trans->entry->description,
                'cost_center' => $trans->costCenter?->name,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => round($runningBalance, 4), // الرصيد بعد هذه الحركة
            ];
        }

        return [
            'account' => Account::find($accountId)->only('id', 'name', 'code'),
            'period' => ['from' => $startDate, 'to' => $endDate],
            'opening_balance' => (float) $openingBalance,
            'transactions' => $formattedTransactions,
            'closing_balance' => (float) $runningBalance,
        ];
    }

    /**
     * تقرير 2: ميزان المراجعة (Trial Balance)
     * يعيد ملخص لكل الحسابات
     */
    public function getTrialBalance(string $startDate, string $endDate): array
    {
        // نستخدم استعلام SQL خام أو Eloquent متقدم للأداء العالي
        // سنقوم بتجميع البيانات حسب الحساب

        $data = JournalEntryDetail::query()
            ->select('account_id')
            ->selectRaw('
                SUM(CASE WHEN journal_entries.date < ? THEN debit - credit ELSE 0 END) as opening_balance,
                SUM(CASE WHEN journal_entries.date >= ? AND journal_entries.date <= ? THEN debit ELSE 0 END) as period_debit,
                SUM(CASE WHEN journal_entries.date >= ? AND journal_entries.date <= ? THEN credit ELSE 0 END) as period_credit
            ', [$startDate, $startDate, $endDate, $startDate, $endDate])
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.status', EntryStatus::Posted)
            ->groupBy('account_id')
            ->with('account:id,name,code,nature') // لجلب اسم الحساب
            ->get();

        $report = $data->map(function ($row) {
            $opening = (float) $row->opening_balance;
            $debit = (float) $row->period_debit;
            $credit = (float) $row->period_credit;
            $closing = $opening + $debit - $credit;

            return [
                'account_id' => $row->account_id,
                'account_code' => $row->account->code,
                'account_name' => $row->account->name,
                'opening_balance' => $opening,
                'debit' => $debit,
                'credit' => $credit,
                'closing_balance' => $closing,
            ];
        });

        // حساب الإجماليات
        return [
            'period' => ['from' => $startDate, 'to' => $endDate],
            'data' => $report,
            'totals' => [
                'debit' => $report->sum('debit'),
                'credit' => $report->sum('credit'),
            ]
        ];
    }
}
