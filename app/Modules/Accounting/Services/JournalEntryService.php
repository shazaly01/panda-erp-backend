<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\DTO\JournalEntryDto;
use App\Modules\Accounting\Enums\EntryStatus;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Core\Services\SequenceService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JournalEntryService
{
    public function __construct(
        protected SequenceService $sequenceService
    ) {}

    /**
     * إنشاء قيد جديد
     */
    public function createEntry(JournalEntryDto $dto): JournalEntry
    {
        // 1. التحقق من التوازن (المنطق المركزي)
        $this->validateBalance($dto);

        return DB::transaction(function () use ($dto) {
            // 2. إنشاء رأس القيد
            $entry = JournalEntry::create([
                'date'           => $dto->date,
                'description'    => $dto->description,
                'currency_id'    => $dto->currency_id,
                'status'         => EntryStatus::Draft, // الافتراضي مسودة
                'source'         => $dto->source ?? 'manual',
                'reference_type' => $dto->reference_type,
                'reference_id'   => $dto->reference_id,
                'created_by'     => Auth::id(),
            ]);

            // 3. إنشاء التفاصيل
            $this->createDetails($entry, $dto->details);

            return $entry->load('details');
        });
    }

    /**
     * تعديل قيد موجود (مسودة)
     * الاستراتيجية: الحذف الكامل للتفاصيل وإعادة إنشائها (Recreation Strategy)
     */
    public function updateEntry(JournalEntry $entry, JournalEntryDto $dto): JournalEntry
    {
        // 1. حماية القيود المرحلة
        if ($entry->status !== EntryStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => ['لا يمكن تعديل القيد لأنه مرحل أو ملغي.'],
            ]);
        }

        // 2. التحقق من التوازن للبيانات الجديدة
        $this->validateBalance($dto);

        return DB::transaction(function () use ($entry, $dto) {
            // تحديث البيانات الأساسية
            $entry->update([
                'date'           => $dto->date,
                'description'    => $dto->description,
                'currency_id'    => $dto->currency_id,
                'source'         => $dto->source ?? $entry->source,
                'reference_type' => $dto->reference_type ?? $entry->reference_type,
                'reference_id'   => $dto->reference_id ?? $entry->reference_id,
            ]);

            // حذف التفاصيل القديمة (Hard Delete)
            $entry->details()->delete();

            // إعادة إنشاء التفاصيل الجديدة
            $this->createDetails($entry, $dto->details);

            return $entry->refresh()->load('details');
        });
    }

    /**
     * تحديث قيد نظامي مُرحّل (مثل مسير الرواتب) مع الاحتفاظ بنفس رقم القيد:
     * يتم تحديث بيانات الرأس وحذف التفاصيل القديمة وإعادة إدراج التفاصيل الجديدة المتوازنة
     */
    public function updatePostedSystemEntry(JournalEntry $entry, JournalEntryDto $dto): JournalEntry
    {
        if (!in_array($entry->status, [EntryStatus::Draft, EntryStatus::Posted], true)) {
            throw ValidationException::withMessages([
                'status' => ['لا يمكن تعديل قيد محاسبي ملغي أو في حالة غير صالحة.'],
            ]);
        }

        if ($entry->source !== 'hr_payroll') {
            throw ValidationException::withMessages([
                'source' => ['هذا الإجراء مخصص لتحديث قيود مسيرات الرواتب فقط.'],
            ]);
        }

        $this->validateBalance($dto);

        return DB::transaction(function () use ($entry, $dto) {
            $entry->update([
                'date'        => $dto->date,
                'description' => $dto->description,
                'currency_id' => $dto->currency_id,
                'posted_at'   => now(),
            ]);

            // حذف السطور القديمة نهائياً وإعادة إدراج السطور المحدثة مع الإبقاء على نفس رقم القيد
            $entry->details()->delete();
            $this->createDetails($entry, $dto->details);

            if ($entry->status === EntryStatus::Draft) {
                $entryNumber = $entry->entry_number ?: $this->sequenceService->generateNumber(
                    'acc_journal_entry',
                    null,
                    null,
                    (string) $dto->date
                );
                $entry->update([
                    'status'       => EntryStatus::Posted,
                    'entry_number' => $entryNumber,
                ]);
            }

            return $entry->refresh()->load('details');
        });
    }

    /**
     * حذف قيد
     */
    public function deleteEntry(JournalEntry $entry): bool
    {
        // حماية القيود المرحلة
        if ($entry->status !== EntryStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => ['لا يمكن حذف قيد مرحل. يجب عمل قيد عكسي بدلاً من ذلك.'],
            ]);
        }

        return DB::transaction(function () use ($entry) {
            // حذف التفاصيل أولاً
            $entry->details()->delete();

            // حذف الرأس (Soft Delete)
            return $entry->delete();
        });
    }

    /**
     * ترحيل القيد (Posting)
     */
    public function postEntry(JournalEntry $entry): JournalEntry
    {
        // التحقق من الحالة
        if ($entry->status !== EntryStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => ['القيد مرحل مسبقاً أو ملغي.'],
            ]);
        }

        // إعادة التحقق من التوازن قبل الترحيل (كإجراء احترازي أخير)
        $debitSum = $entry->details()->sum('debit');
        $creditSum = $entry->details()->sum('credit');

        if (abs($debitSum - $creditSum) > 0.0001) {
            throw ValidationException::withMessages([
                'balance' => ['لا يمكن ترحيل قيد غير متزن.'],
            ]);
        }

        return DB::transaction(function () use ($entry) {
            $entryDate = $entry->date instanceof CarbonInterface
                ? $entry->date->toDateString()
                : (string) $entry->date;

            // استدعاء الكود العالمي لتوليد رقم القيد بناءً على تاريخ القيد الفعلي
            $entryNumber = $this->sequenceService->generateNumber(
                'acc_journal_entry',
                null,
                null,
                $entryDate
            );

            $entry->update([
                'status'       => EntryStatus::Posted,
                'entry_number' => $entryNumber,
                'posted_at'    => now(),
            ]);

            return $entry;
        });
    }

    /**
     * دالة مساعدة لإنشاء التفاصيل لتجنب تكرار الكود
     */
    protected function createDetails(JournalEntry $entry, array $detailsDtoArray): void
    {
        foreach ($detailsDtoArray as $detail) {
            $entry->details()->create([
                'account_id'     => $detail->account_id,
                'cost_center_id' => $detail->cost_center_id,
                'debit'          => $detail->debit,
                'credit'         => $detail->credit,
                'description'    => $detail->description,
                'party_type'     => $detail->party_type,
                'party_id'       => $detail->party_id,
            ]);
        }
    }

    /**
     * التحقق من توازن القيد
     * يرمي ValidationException (422) للواجهة الأمامية
     */
    protected function validateBalance(JournalEntryDto $dto): void
    {
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($dto->details as $detail) {
            $totalDebit += $detail->debit;
            $totalCredit += $detail->credit;
        }

        if (abs($totalDebit - $totalCredit) > 0.0001) {
            throw ValidationException::withMessages([
                'balance' => ["القيد غير متزن! إجمالي المدين ({$totalDebit}) لا يساوي إجمالي الدائن ({$totalCredit})."],
            ]);
        }
    }
}