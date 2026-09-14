<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Enums\VoucherStatus;
use App\Modules\Accounting\Enums\VoucherType;
use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Models\Box;
use App\Modules\Accounting\Models\CostCenter;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\JournalEntryDetail;
use App\Modules\Accounting\Models\Voucher;
use App\Modules\Accounting\Models\VoucherDetail;
use App\Modules\Core\Services\SequenceService;
use App\Modules\Purchasing\Enums\BillStatus;
use App\Modules\Purchasing\Models\PurchaseBill;
use Exception;
use Illuminate\Support\Facades\DB;

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
        return DB::transaction(function () use ($data): Voucher {
            // 1. تجهيز بيانات الترقيم
            $branch = CostCenter::findOrFail($data['branch_id']);
            $prefix = $branch->code_prefix;

            // تحديد كود الترقيم بحسب نوع السند (PAYMENT أو RECEIPT)
            $documentCode = strtolower((string) ($data['type'] instanceof VoucherType ? $data['type']->value : $data['type'])) === 'payment'
                ? 'acc_payment'
                : 'acc_receipt';

            // 2. توليد الرقم التسلسلي
            $number = $this->sequenceService->generateNumber(
                $documentCode,
                null,
                $prefix
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
                'exchange_rate'   => $data['exchange_rate'] ?? 1.0,
                'amount'          => $data['amount'],
                'status'          => VoucherStatus::Draft,
                'created_by'      => auth()->id(),
            ]);

            // 4. إنشاء التفاصيل (السطور) مع بيانات الطرف والمستند المرجعي
            foreach ($data['details'] as $detail) {
                $voucher->details()->create([
                    'account_id'     => $detail['account_id'],
                    'cost_center_id' => $detail['cost_center_id'] ?? null,
                    'amount'         => $detail['amount'],
                    'description'    => $detail['description'] ?? null,
                    'party_type'     => $detail['party_type'] ?? null,
                    'party_id'       => isset($detail['party_id']) ? (string) $detail['party_id'] : null,
                    'reference_type' => $detail['reference_type'] ?? null,
                    'reference_id'   => isset($detail['reference_id']) ? (int) $detail['reference_id'] : null,
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
        if (! $voucher->isDraft()) {
            throw new Exception('لا يمكن تعديل السند لأنه ليس في حالة مسودة.');
        }

        return DB::transaction(function () use ($voucher, $data): Voucher {
            // تحديث رأس السند
            $voucher->update([
                'branch_id'       => $data['branch_id'] ?? $voucher->branch_id,
                'date'            => $data['date'],
                'payee_name'      => $data['payee_name'] ?? $voucher->payee_name,
                'description'     => $data['description'] ?? $voucher->description,
                'box_id'          => $data['box_id'] ?? null,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'currency_id'     => $data['currency_id'],
                'exchange_rate'   => $data['exchange_rate'] ?? 1.0,
                'amount'          => $data['amount'],
            ]);

            // حذف التفاصيل السابقة وإعادة إنشائها بالحقول المحدثة
            $voucher->details()->delete();

            foreach ($data['details'] as $detail) {
                $voucher->details()->create([
                    'account_id'     => $detail['account_id'],
                    'cost_center_id' => $detail['cost_center_id'] ?? null,
                    'amount'         => $detail['amount'],
                    'description'    => $detail['description'] ?? null,
                    'party_type'     => $detail['party_type'] ?? null,
                    'party_id'       => isset($detail['party_id']) ? (string) $detail['party_id'] : null,
                    'reference_type' => $detail['reference_type'] ?? null,
                    'reference_id'   => isset($detail['reference_id']) ? (int) $detail['reference_id'] : null,
                ]);
            }

            return $voucher->refresh();
        });
    }

    /**
     * ترحيل السند وتحويله لقيد محاسبي وتسوية الفواتير المرجعية
     */
    public function postVoucher(Voucher $voucher): Voucher
    {
        if ($voucher->isPosted()) {
            throw new Exception('هذا السند مرحل مسبقاً.');
        }

        return DB::transaction(function () use ($voucher): Voucher {
            // 1. تحديد الحساب الرئيسي (الخزينة أو البنك)
            $mainAccount = null;
            $partyType = null;
            $partyId = null;

            if ($voucher->box_id) {
                $box = Box::findOrFail($voucher->box_id);
                $mainAccount = $box->account_id;
                $partyType = Box::class;
                $partyId = $box->id;
            } elseif ($voucher->bank_account_id) {
                $bank = BankAccount::findOrFail($voucher->bank_account_id);
                $mainAccount = $bank->account_id;
                $partyType = BankAccount::class;
                $partyId = $bank->id;
            }

            if (! $mainAccount) {
                throw new Exception('لا يوجد حساب مالي مرتبط بالخزينة أو البنك المختار.');
            }

            // 2. توليد رقم القيد وإنشاء رأس القيد المحاسبي
            $entryNumber = $this->sequenceService->generateNumber('acc_journal_entry');

            $journalEntry = JournalEntry::create([
                'entry_number' => $entryNumber,
                'date'         => $voucher->date,
                'description'  => "سند {$voucher->type->label()} رقم {$voucher->number} - {$voucher->description}",
                'status'       => 'posted',
                'source'       => $voucher->type->value,
                'source_id'    => $voucher->id,
                'posted_at'    => now(),
                'created_by'   => auth()->id(),
            ]);

            // 3. بناء أطراف القيد (Dr & Cr)
            $isPayment = ($voucher->type === VoucherType::Payment);

            // أ) الطرف الرئيسي (الخزينة أو البنك)
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id'       => $mainAccount,
                'debit'            => $isPayment ? 0.0 : $voucher->amount,
                'credit'           => $isPayment ? $voucher->amount : 0.0,
                'cost_center_id'   => $voucher->branch_id,
                'description'      => $voucher->description,
                'party_type'       => $partyType,
                'party_id'         => $partyId ? (string) $partyId : null,
            ]);

            // ب) الأطراف التفصيلية (الموردين / العملاء / المصروفات)
            foreach ($voucher->details as $detail) {
                JournalEntryDetail::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id'       => $detail->account_id,
                    'debit'            => $isPayment ? $detail->amount : 0.0,
                    'credit'           => $isPayment ? 0.0 : $detail->amount,
                    'cost_center_id'   => $detail->cost_center_id,
                    'description'      => $detail->description ?? $voucher->description,
                    'party_type'       => $detail->party_type,
                    'party_id'         => $detail->party_id ? (string) $detail->party_id : null,
                ]);
            }

            // 4. تسوية الفواتير المرجعية وتحديث مبالغ السداد وحالاتها
            $this->settleReferencedDocuments($voucher);

            // 5. تحديث حالة السند
            $voucher->update([
                'status'    => VoucherStatus::Posted,
                'posted_by' => auth()->id(),
                'posted_at' => now(),
            ]);

            return $voucher;
        });
    }

    /**
     * تسوية المستندات والفواتير المرتبطة بسطور السند
     */
    protected function settleReferencedDocuments(Voucher $voucher): void
    {
        $voucher->loadMissing('details.reference');

        foreach ($voucher->details as $detail) {
            if (! $detail->reference_type || ! $detail->reference_id) {
                continue;
            }

            $reference = $detail->reference;

            if ($reference instanceof PurchaseBill) {
                $settledAmount = (float) $detail->amount;
                $newPaidAmount = round((float) $reference->paid_amount + $settledAmount, 4);
                $newRemainingAmount = max(0.0, round((float) $reference->total_amount - $newPaidAmount, 4));

                $newStatus = $newRemainingAmount <= 0.0001
                    ? BillStatus::PAID
                    : BillStatus::PARTIALLY_PAID;

                $reference->update([
                    'paid_amount'      => $newPaidAmount,
                    'remaining_amount' => $newRemainingAmount,
                    'status'           => $newStatus,
                    'updated_by'       => auth()->id() ?? $voucher->created_by,
                ]);
            }
        }
    }
}