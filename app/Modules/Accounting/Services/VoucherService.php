<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Voucher;
use App\Modules\Accounting\Models\VoucherDetail;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\JournalEntryDetail;
use App\Modules\Accounting\Models\CostCenter;
use App\Modules\Accounting\Models\Box;
use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Enums\VoucherType;
use App\Modules\Accounting\Enums\VoucherStatus;
use App\Modules\Core\Services\SequenceService;
use Illuminate\Support\Facades\DB;
use Exception;

class VoucherService
{
    public function __construct(
        protected SequenceService $sequenceService
    ) {}

    /**
     * إنشاء سند جديد (مسودة)
     */
    public function createVoucher(array $data): Voucher
    {
        return DB::transaction(function () use ($data) {

            // 1. تجهيز بيانات الترقيم
            // نجلب مركز التكلفة لنعرف "كود الفرع" (RY, JD)
            $branch = CostCenter::findOrFail($data['branch_id']);
            $prefix = $branch->code_prefix; // يمكن أن يكون null

            // نحدد نوع الترقيم بناء على نوع السند (PAYMENT, RECEIPT)
            // نستخدم القيمة النصية من الـ Enum (مثلاً 'payment') ونحولها لأحرف كبيرة
            $sequenceType = strtoupper($data['type']);

            // 2. توليد الرقم باستخدام السيرفس الذكي
            $number = $this->sequenceService->generateNumber(
                $sequenceType,  // الموديل (PAYMENT أو RECEIPT)
                null,    // ID الفرع لفصل العداد
                $prefix         // RY أو JD
            );

            // 3. إنشاء رأس السند
            $voucher = Voucher::create([
                'branch_id'       => $data['branch_id'],
                'type'            => $data['type'],
                'number'          => $number,
                'date'            => $data['date'],
                'payee_name'      => $data['payee_name'],
                'description'     => $data['description'] ?? null,
                'box_id'          => $data['box_id'] ?? null,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'currency_id'     => $data['currency_id'],
                'exchange_rate'   => $data['exchange_rate'] ?? 1,
                'amount'          => $data['amount'],
                'status'          => VoucherStatus::Draft, // يبدأ كمسودة
                'created_by'      => auth()->id(),
            ]);

            // 4. إنشاء التفاصيل (السطور)
           foreach ($data['details'] as $detail) {
                $voucher->details()->create([
                    'account_id'     => $detail['account_id'],
                    'cost_center_id' => $detail['cost_center_id'] ?? null,
                    'amount'         => $detail['amount'],
                    'description'    => $detail['description'] ?? null,
                    'party_type'     => $detail['party_type'] ?? null, // 👈
                    'party_id'       => isset($detail['party_id']) ? (string) $detail['party_id'] : null, // 👈
                ]);
            }

            return $voucher;
        });
    }

    /**
     * تحديث السند (مسموح فقط إذا كان مسودة)
     */
    public function updateVoucher(Voucher $voucher, array $data): Voucher
    {
        if (!$voucher->isDraft()) {
            throw new Exception("لا يمكن تعديل السند لأنه ليس في حالة مسودة.");
        }

        return DB::transaction(function () use ($voucher, $data) {
            // تحديث الرأس
            $voucher->update([
                'date'            => $data['date'],
                'payee_name'      => $data['payee_name'] ?? $voucher->payee_name,
                'description'     => $data['description'] ?? $voucher->description,
                'box_id'          => $data['box_id'] ?? null,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'currency_id'     => $data['currency_id'],
                'exchange_rate'   => $data['exchange_rate'] ?? 1,
                'amount'          => $data['amount'],
            ]);

            // تحديث التفاصيل (الأسهل: حذف القديم وإنشاء الجديد)
            $voucher->details()->delete();

           foreach ($data['details'] as $detail) {
                $voucher->details()->create([
                    'account_id'     => $detail['account_id'],
                    'cost_center_id' => $detail['cost_center_id'] ?? null,
                    'amount'         => $detail['amount'],
                    'description'    => $detail['description'] ?? null,
                    'party_type'     => $detail['party_type'] ?? null, // 👈
                    'party_id'       => isset($detail['party_id']) ? (string) $detail['party_id'] : null, // 👈
                ]);
            }

            return $voucher->refresh();
        });
    }

   /**
     * ترحيل السند (تحويله لقيد محاسبي)
     * هذا هو أهم جزء في النظام
     */
    public function postVoucher(Voucher $voucher): Voucher
    {
        if ($voucher->isPosted()) {
            throw new Exception("هذا السند مرحل مسبقاً.");
        }

        return DB::transaction(function () use ($voucher) {

            // 1. تحديد الحساب "الرئيسي" (الخزينة أو البنك)
            // هذا الحساب سيكون الطرف الدائن في الصرف، والمدين في القبض
            $mainAccount = null;
            $partyType = null;
            $partyId = null;

            if ($voucher->box_id) {
                $box = Box::findOrFail($voucher->box_id);
                $mainAccount = $box->account_id;
                $partyType = Box::class; // <-- هنا نعرف النظام أنها خزينة
                $partyId = $box->id;     // <-- وهنا رقمها
            } elseif ($voucher->bank_account_id) {
                $bank = BankAccount::findOrFail($voucher->bank_account_id);
                $mainAccount = $bank->account_id;
                $partyType = BankAccount::class; // <-- هنا نعرف النظام أنه بنك
                $partyId = $bank->id;            // <-- وهنا رقمه
            }

            if (!$mainAccount) {
                throw new Exception("لا يوجد حساب مالي مرتبط بالخزينة أو البنك المختار.");
            }

            // 2. إنشاء رأس القيد (Journal Entry)
           $entryNumber = $this->sequenceService->generateNumber(
                \App\Modules\Accounting\Models\JournalEntry::class
            );

            // 3. إنشاء رأس القيد (Journal Entry) مع الرقم المولد
           $journalEntry = JournalEntry::create([
                'entry_number' => $entryNumber,
                'date'         => $voucher->date,
                'description'  => "سند {$voucher->type->label()} رقم {$voucher->number} - {$voucher->description}",
                'status'       => 'posted',
                'source'       => $voucher->type->value, // سيسجل 'payment' أو 'receipt'
                'source_id'    => $voucher->id,         // 👈 هذا هو الرابط لفتح السند لاحقاً
                'posted_at'    => now(),
                'created_by'   => auth()->id(),
            ]);

            // 3. بناء أطراف القيد (Dr & Cr)
            $isPayment = ($voucher->type === VoucherType::Payment);

            // أ) الطرف الرئيسي (الخزينة/البنك)
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id'       => $mainAccount,
                'debit'            => $isPayment ? 0 : $voucher->amount,
                'credit'           => $isPayment ? $voucher->amount : 0,
                'cost_center_id'   => $voucher->branch_id,
                'description'      => $voucher->description,
                'party_type'       => $partyType, // <-- تمت الإضافة!
                'party_id'         => $partyId,   // <-- تمت الإضافة!
            ]);

            // ب) الأطراف التفصيلية (المصروفات أو الإيرادات)
            foreach ($voucher->details as $detail) {
                // ملاحظة: إذا كانت الأطراف التفصيلية تحتوي على عملاء أو موردين
                // يجب أيضاً تعديل VoucherDetailDto ليدعم إرسال party_type و party_id
                JournalEntryDetail::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id'       => $detail->account_id,
                    'debit'            => $isPayment ? $detail->amount : 0,
                    'credit'           => $isPayment ? 0 : $detail->amount,
                    'cost_center_id'   => $detail->cost_center_id,
                    'description'      => $detail->description ?? $voucher->description,
                    'party_type'       => $detail->party_type, // 👈 نقل النوع للقيد المحاسبي
                    'party_id'         => $detail->party_id ? (string) $detail->party_id : null, // 👈 نقل الـ ID كـ String
                    // حالياً نتركها null للسطور التفصيلية (لأننا نفترض أنها مصاريف/إيرادات عادية)
                    // لكن إذا كانت الواجهة تدعم اختيار عميل في السطور، ستحتاج لإضافتها هنا.
                ]);
            }

            // 4. تحديث حالة السند
            $voucher->update([
                'status'    => VoucherStatus::Posted,
                'posted_by' => auth()->id(),
                'posted_at' => now(),
            ]);

            return $voucher;
        });
    }

}
