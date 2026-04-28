<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\DTO\JournalEntryDto;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Enums\EntryStatus;
use App\Modules\Core\Services\SequenceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Exception;

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
                'date'        => $dto->date,
                'description' => $dto->description,
                'currency_id' => $dto->currency_id,
                'status'      => EntryStatus::Draft, // الافتراضي مسودة
                'source'      => 'manual',
                'created_by'  => Auth::id(),
            ]);

            // 3. إنشاء التفاصيل
            $this->createDetails($entry, $dto->details);

            return $entry->load('details');
        });
    }

    /**
     * تعديل قيد موجود
     * الاستراتيجية: الحذف الكامل للتفاصيل وإعادة إنشائها (Recreation Strategy)
     */
    public function updateEntry(JournalEntry $entry, JournalEntryDto $dto): JournalEntry
    {
        // 1. حماية القيود المرحلة
        if ($entry->status !== EntryStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => ['لا يمكن تعديل القيد لأنه مرحل أو ملغي.']
            ]);
        }

        // 2. التحقق من التوازن للبيانات الجديدة
        $this->validateBalance($dto);

        return DB::transaction(function () use ($entry, $dto) {
            // تحديث البيانات الأساسية
            $entry->update([
                'date'        => $dto->date,
                'description' => $dto->description,
                'currency_id' => $dto->currency_id,
            ]);

            // حذف التفاصيل القديمة (Hard Delete)
            // ملاحظة: JournalEntryDetail لا يستخدم SoftDeletes، لذا سيتم الحذف نهائياً وهو المطلوب
            $entry->details()->delete();

            // إعادة إنشاء التفاصيل الجديدة
            $this->createDetails($entry, $dto->details);

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
                'status' => ['لا يمكن حذف قيد مرحل. يجب عمل قيد عكسي بدلاً من ذلك.']
            ]);
        }

        return DB::transaction(function () use ($entry) {
            // حذف التفاصيل أولاً (اختياري إذا كان هناك Cascade في الداتابيز، لكن أضمن في الكود)
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
                'status' => ['القيد مرحل مسبقاً أو ملغي.']
            ]);
        }

        // إعادة التحقق من التوازن قبل الترحيل (كإجراء احترازي أخير)
        $debitSum = $entry->details()->sum('debit');
        $creditSum = $entry->details()->sum('credit');

        if (abs($debitSum - $creditSum) > 0.0001) {
             throw ValidationException::withMessages([
                'balance' => ["لا يمكن ترحيل قيد غير متزن."]
            ]);
        }

        return DB::transaction(function () use ($entry) {
            // 🌟 التعديل المعماري هنا: استدعاء الكود العالمي بدلاً من مسار الموديل
            $entryNumber = $this->sequenceService->generateNumber(
                modelClass: 'acc_journal_entry',
                branchId: null
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
                'balance' => ["القيد غير متزن! إجمالي المدين ({$totalDebit}) لا يساوي إجمالي الدائن ({$totalCredit})."]
            ]);
        }
    }
}
