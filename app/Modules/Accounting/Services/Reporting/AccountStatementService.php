<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Services\Reporting;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalEntryDetail;
use App\Modules\Accounting\Enums\AccountNature;
use App\Modules\Accounting\Enums\EntryStatus;

class AccountStatementService
{
    /**
     * استخراج كشف الحساب الشامل
     */
    public function getStatement(
        int $accountId,
        string $fromDate,
        string $toDate,
        ?int $costCenterId = null,
        ?string $partyType = null,
        ?int $partyId = null,
        bool $includeDrafts = false // [جديد] خيار تضمين المسودات
    ): array {
        // 1. جلب معلومات الحساب لمعرفة طبيعته
        $account = Account::findOrFail($accountId);
        $nature = $account->nature;

        // 2. حساب الرصيد الافتتاحي (قبل تاريخ البداية)
        $openingBalance = $this->calculateOpeningBalance(
            $accountId, $fromDate, $nature, $costCenterId, $partyType, $partyId, $includeDrafts
        );

        // 3. جلب حركات الفترة المحددة
        $transactions = $this->fetchPeriodTransactions(
            $accountId, $fromDate, $toDate, $costCenterId, $partyType, $partyId, $includeDrafts
        );

        // 4. معالجة الرصيد التراكمي (Running Balance) وتجهيز المخرجات
        $processedTransactions = [];
        $runningBalance = $openingBalance;
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($transactions as $tx) {
            $debit = (float) $tx->debit;
            $credit = (float) $tx->credit;

            $totalDebit += $debit;
            $totalCredit += $credit;

            // معادلة الرصيد بناءً على طبيعة الحساب
            if ($nature === AccountNature::Debit) {
                $runningBalance += ($debit - $credit);
            } else {
                $runningBalance += ($credit - $debit);
            }

          $processedTransactions[] = [
                'id'               => $tx->journal_entry_id,
                'source_id'        => $tx->entry->source_id, // 👈 أضفنا هذا السطر
                'date'             => $tx->entry->date->format('Y-m-d'),
                'entry_number'     => $tx->entry->entry_number
                  ?? ($tx->entry->status === EntryStatus::Posted
                      ? 'POSTED-#' . $tx->entry->id
                      : 'DRAFT-#' . $tx->entry->id),
                'status'           => $tx->entry->status->value,
                'source'           => $tx->entry->source->value, // 👈 سيأتي الآن كـ 'payment' أو 'receipt' أو 'manual'
                'description'      => $tx->description ?? $tx->entry->description,
                'opposite_account' => $this->getOppositeAccountName($tx->journal_entry_id, $tx->id),
                'debit'            => $debit,
                'credit'           => $credit,
                'balance'          => $runningBalance,
            ];
        }

        // 5. إرجاع التقرير جاهزاً للـ Resource أو הـ Vue
        return [
            'account_info' => [
                'name'   => $account->name,
                'code'   => $account->code,
                'nature' => $nature->label(),
            ],
            'summary' => [
                'opening_balance' => $openingBalance,
                'total_debit'     => $totalDebit,
                'total_credit'    => $totalCredit,
                'closing_balance' => $runningBalance,
            ],
            'transactions' => $processedTransactions
        ];
    }

    /**
     * استعلام الرصيد الافتتاحي (سريع جداً باستخدام SUM في قاعدة البيانات مباشرة)
     */
    protected function calculateOpeningBalance(
        int $accountId, string $fromDate, AccountNature $nature,
        ?int $costCenterId, ?string $partyType, ?int $partyId,
        bool $includeDrafts
    ): float {
        // [جديد] تحديد الحالات المطلوبة
        $statuses = $includeDrafts
            ? [EntryStatus::Posted->value, EntryStatus::Draft->value]
            : [EntryStatus::Posted->value];

        $totals = JournalEntryDetail::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_details.journal_entry_id')
            ->whereIn('journal_entries.status', $statuses) // [تعديل] استخدام whereIn
            ->where('journal_entries.date', '<', $fromDate)
            ->where('journal_entry_details.account_id', $accountId)
            ->when($costCenterId, fn($q) => $q->where('journal_entry_details.cost_center_id', $costCenterId))
            ->when($partyType && $partyId, fn($q) => $q->where('journal_entry_details.party_type', $partyType)
                                                       ->where('journal_entry_details.party_id', $partyId))
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();

        $debit = (float) ($totals->total_debit ?? 0);
        $credit = (float) ($totals->total_credit ?? 0);

        return $nature === AccountNature::Debit ? ($debit - $credit) : ($credit - $debit);
    }

    /**
     * استعلام جلب تفاصيل حركات الفترة بشكل مرتب
     */
    protected function fetchPeriodTransactions(
        int $accountId, string $fromDate, string $toDate,
        ?int $costCenterId, ?string $partyType, ?int $partyId,
        bool $includeDrafts
    ) {
        // [جديد] تحديد الحالات المطلوبة
        $statuses = $includeDrafts
            ? [EntryStatus::Posted->value, EntryStatus::Draft->value]
            : [EntryStatus::Posted->value];

        return JournalEntryDetail::query()
            ->select('journal_entry_details.*') // لضمان عدم تداخل أعمدة الـ Join
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_details.journal_entry_id')
            ->whereIn('journal_entries.status', $statuses) // [تعديل] استخدام whereIn
            ->whereBetween('journal_entries.date', [$fromDate, $toDate])
            ->where('journal_entry_details.account_id', $accountId)
            ->when($costCenterId, fn($q) => $q->where('journal_entry_details.cost_center_id', $costCenterId))
            ->when($partyType && $partyId, fn($q) => $q->where('journal_entry_details.party_type', $partyType)
                                                       ->where('journal_entry_details.party_id', $partyId))
            ->with('entry') // Eager Loading لجلب بيانات الرأس بدون N+1 Problem
            ->orderBy('journal_entries.date', 'asc')
            ->orderBy('journal_entries.id', 'asc') // ترتيب إضافي في حال وجود قيدين في نفس اليوم
            ->get();
    }

    /**
     * دالة مساعدة ذكية لاستخراج "الحساب المقابل"
     */
    protected function getOppositeAccountName(int $entryId, int $currentDetailId): string
    {
        // نجلب الأسطر الأخرى في نفس القيد
        $otherDetails = JournalEntryDetail::with('account')
            ->where('journal_entry_id', $entryId)
            ->where('id', '!=', $currentDetailId)
            ->get();

        if ($otherDetails->count() === 1) {
            // إذا كان هناك سطر واحد مقابل، نعيد اسمه
            return $otherDetails->first()->account->name;
        }

        // إذا كان القيد مركب (يحتوي على عدة حسابات مقابلة)
        return 'مذكورين (قيد مركب)';
    }
}
