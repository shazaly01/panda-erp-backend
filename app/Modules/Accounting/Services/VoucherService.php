<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Enums\EntryStatus;
use App\Modules\Accounting\Enums\VoucherStatus;
use App\Modules\Accounting\Enums\VoucherType;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Models\Box;
use App\Modules\Accounting\Models\CostCenter;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\JournalEntryDetail;
use App\Modules\Accounting\Models\Voucher;
use App\Modules\Core\Services\SequenceService;
use App\Modules\Purchasing\Enums\BillStatus;
use App\Modules\Purchasing\Models\PurchaseBill;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
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
            $typeValue = strtolower((string) ($data['type'] instanceof VoucherType ? $data['type']->value : $data['type']));

            // التحقق من كفاية رصيد الخزينة أو البنك في حال كان السند سند صرف
            if ($typeValue === 'payment') {
                $this->ensureSufficientBalance(
                    isset($data['box_id']) ? (int) $data['box_id'] : null,
                    isset($data['bank_account_id']) ? (int) $data['bank_account_id'] : null,
                    (float) $data['amount']
                );
            }

            // 1. تجهيز بيانات الترقيم
            $branch = CostCenter::findOrFail($data['branch_id']);
            $prefix = $branch->code_prefix;

            // تحديد كود الترقيم بحسب نوع السند (PAYMENT أو RECEIPT)
            $documentCode = $typeValue === 'payment'
                ? 'acc_payment'
                : 'acc_receipt';

            // 2. توليد الرقم التسلسلي بالاعتماد على تاريخ السند المدخل
            $number = $this->sequenceService->generateNumber(
                $documentCode,
                null,
                $prefix,
                (string) $data['date']
            );

            // 3. إنشاء رأس السند
            $voucher = Voucher::create([
                'branch_id'       => $data['branch_id'],
                'type'            => $data['type'],
                'number'          => $number,
                'paper_ref'       => $data['paper_ref'] ?? null,
                'bank_ref_number' => $data['bank_ref_number'] ?? null,
                'date'            => $data['date'],
                'payee_name'      => $data['payee_name'],
                'description'     => $data['description'] ?? null,
                'box_id'          => $data['box_id'] ?? null,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'currency_id'     => $data['currency_id'],
                'exchange_rate'   => $data['exchange_rate'] ?? 1.0,
                'amount'          => $data['amount'],
                'status'          => VoucherStatus::Draft,
                'created_by'      => Auth::id(),
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
     * تحديث السند (مسودة أو مُرحّل) مع الحفاظ على نفس رقم السند ورقم القيد المرتبط
     */
    public function updateVoucher(Voucher $voucher, array $data): Voucher
    {
        if ($voucher->status === VoucherStatus::Void) {
            throw new Exception('لا يمكن تعديل سند ملغي.');
        }

        return DB::transaction(function () use ($voucher, $data): Voucher {
            $wasPosted = $voucher->isPosted();
            $oldBoxId = $voucher->box_id ? (int) $voucher->box_id : null;
            $oldBankId = $voucher->bank_account_id ? (int) $voucher->bank_account_id : null;
            $oldAmount = (float) $voucher->amount;

            $newBoxId = isset($data['box_id']) && $data['box_id'] ? (int) $data['box_id'] : null;
            $newBankId = isset($data['bank_account_id']) && $data['bank_account_id'] ? (int) $data['bank_account_id'] : null;

            // التحقق من كفاية الرصيد في حال كان السند سند صرف
            if ($voucher->type === VoucherType::Payment) {
                $previousDeductedAmount = 0.0;

                // إذا كان السند مرحلاً مسبقاً وعلى نفس الخزينة أو البنك، نضيف مبلغه السابق للرصيد المتاح
                if ($wasPosted) {
                    if ($newBoxId !== null && $newBoxId === $oldBoxId) {
                        $previousDeductedAmount = $oldAmount;
                    } elseif ($newBankId !== null && $newBankId === $oldBankId) {
                        $previousDeductedAmount = $oldAmount;
                    }
                }

                $this->ensureSufficientBalance(
                    $newBoxId,
                    $newBankId,
                    (float) $data['amount'],
                    $previousDeductedAmount
                );
            }

            // عكس تسوية الفواتير المرجعية القديمة قبل استبدال السطور إذا كان السند مرحلاً
            if ($wasPosted) {
                $this->reverseReferencedDocuments($voucher);
            }

            // تحديث رأس السند مع الحفاظ على نفس الرقم والحالة
            $voucher->update([
                'branch_id'       => $data['branch_id'] ?? $voucher->branch_id,
                'date'            => $data['date'],
                'paper_ref'       => array_key_exists('paper_ref', $data) ? $data['paper_ref'] : $voucher->paper_ref,
                'bank_ref_number' => array_key_exists('bank_ref_number', $data) ? $data['bank_ref_number'] : $voucher->bank_ref_number,
                'payee_name'      => array_key_exists('payee_name', $data) ? $data['payee_name'] : $voucher->payee_name,
                'description'     => array_key_exists('description', $data) ? $data['description'] : $voucher->description,
                'box_id'          => $newBoxId,
                'bank_account_id' => $newBankId,
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

            $voucher->refresh();
            $voucher->load('details');

            // إذا كان السند مرحلاً، نحدث القيد المحاسبي المرتبط مباشرة بنفس الرقم ونعيد تسوية الفواتير
            if ($wasPosted) {
                $this->syncPostedJournalEntry($voucher);
                $this->settleReferencedDocuments($voucher);
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
            // التحقق النهائي من كفاية الرصيد قبل الترحيل الفعلي في دفتر الأستاذ
            if ($voucher->type === VoucherType::Payment) {
                $this->ensureSufficientBalance(
                    $voucher->box_id ? (int) $voucher->box_id : null,
                    $voucher->bank_account_id ? (int) $voucher->bank_account_id : null,
                    (float) $voucher->amount
                );
            }

            $voucher->loadMissing('details');

            // إنشاء القيد المحاسبي المرتبط بالسند
            $this->createJournalEntryForVoucher($voucher);

            // تسوية الفواتير المرجعية وتحديث مبالغ السداد وحالاتها
            $this->settleReferencedDocuments($voucher);

            // تحديث حالة السند
            $voucher->update([
                'status'    => VoucherStatus::Posted,
                'posted_by' => Auth::id(),
                'posted_at' => Carbon::now(),
            ]);

            return $voucher->refresh();
        });
    }

    /**
     * إلغاء ترحيل السند وحذف القيد المحاسبي المرتبط وإعادته لمسودة
     */
    public function unpostVoucher(Voucher $voucher): Voucher
    {
        if (! $voucher->isPosted()) {
            throw new Exception('لا يمكن إلغاء الترحيل لأن السند ليس في حالة ترحيل.');
        }

        return DB::transaction(function () use ($voucher): Voucher {
            // عكس تسوية الفواتير المرجعية
            $this->reverseReferencedDocuments($voucher);

            // حذف القيود اليومية المرتبطة بهذا السند نهائياً
            $this->deleteLinkedJournalEntries($voucher);

            // إعادة حالة السند إلى مسودة وتصفير بيانات الترحيل
            $voucher->update([
                'status'    => VoucherStatus::Draft,
                'posted_by' => null,
                'posted_at' => null,
            ]);

            return $voucher->refresh();
        });
    }

    /**
     * حذف السند (مسودة أو مُرحّل حسب الصلاحية) مع حذف قيده المحاسبي وعكس التسويات
     */
    public function deleteVoucher(Voucher $voucher): void
    {
        DB::transaction(function () use ($voucher): void {
            if ($voucher->isPosted()) {
                $this->reverseReferencedDocuments($voucher);
                $this->deleteLinkedJournalEntries($voucher);
            }

            $voucher->details()->delete();
            $voucher->delete();
        });
    }

    /**
     * إنشاء قيد يومية جديد للسند عند الترحيل
     */
    protected function createJournalEntryForVoucher(Voucher $voucher): JournalEntry
    {
        $entryNumber = $this->sequenceService->generateNumber(
            'acc_journal_entry',
            null,
            null,
            (string) $voucher->date
        );

        $journalEntry = JournalEntry::create([
            'entry_number' => $entryNumber,
            'date'         => $voucher->date,
            'description'  => "سند {$voucher->type->label()} رقم {$voucher->number} - {$voucher->description}",
            'currency_id'  => $voucher->currency_id,
            'status'       => EntryStatus::Posted,
            'source'       => $voucher->type->value,
            'source_id'    => $voucher->id,
            'posted_at'    => Carbon::now(),
            'created_by'   => Auth::id(),
        ]);

        $this->createJournalEntryLines($journalEntry, $voucher);

        return $journalEntry;
    }

    /**
     * تحديث القيد اليومي المرتبط بسند مرحّل مع الحفاظ على نفس رقم القيد
     */
    protected function syncPostedJournalEntry(Voucher $voucher): void
    {
        $journalEntry = $this->findLinkedJournalEntries($voucher)->first();

        if (! $journalEntry) {
            $this->createJournalEntryForVoucher($voucher);
            return;
        }

        // تحديث بيانات رأس القيد فقط مع الإبقاء على نفس entry_number و id
        $journalEntry->update([
            'date'        => $voucher->date,
            'description' => "سند {$voucher->type->label()} رقم {$voucher->number} - {$voucher->description}",
            'currency_id' => $voucher->currency_id,
            'source_id'   => $voucher->id,
        ]);

        // حذف التفاصيل القديمة وإعادة بنائها
        $journalEntry->details()->delete();
        $this->createJournalEntryLines($journalEntry, $voucher);
    }

    /**
     * بناء أسطر المدين والدائن للقيد المحاسبي من بيانات السند
     */
    protected function createJournalEntryLines(JournalEntry $journalEntry, Voucher $voucher): void
    {
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
    }

    /**
     * جلب القيود اليومية المرتبطة بالسند (بما فيها القيود القديمة التي لم يُحفظ فيها source_id)
     */
    protected function findLinkedJournalEntries(Voucher $voucher)
    {
        $entries = JournalEntry::where('source', $voucher->type->value)
            ->where('source_id', $voucher->id)
            ->get();

        if ($entries->isEmpty() && $voucher->number) {
            $entries = JournalEntry::where('source', $voucher->type->value)
                ->whereNull('source_id')
                ->where('description', 'like', "%رقم {$voucher->number} -%")
                ->get();
        }

        return $entries;
    }

    /**
     * حذف القيود اليومية المرتبطة بالسند نهائياً
     */
    protected function deleteLinkedJournalEntries(Voucher $voucher): void
    {
        $journalEntries = $this->findLinkedJournalEntries($voucher);

        foreach ($journalEntries as $entry) {
            $entry->update(['status' => EntryStatus::Draft]);
            $entry->details()->delete();
            $entry->forceDelete();
        }
    }

    /**
     * التحقق من كفاية رصيد الخزينة أو الحساب البنكي قبل الصرف
     */
    protected function ensureSufficientBalance(
        ?int $boxId,
        ?int $bankAccountId,
        float $amount,
        float $creditBackAmount = 0.0
    ): void {
        $accountId = null;
        $sourceLabel = '';

        if ($boxId) {
            $box = Box::findOrFail($boxId);
            $accountId = $box->account_id;
            $sourceLabel = "الخزينة ({$box->name})";
        } elseif ($bankAccountId) {
            $bank = BankAccount::findOrFail($bankAccountId);
            $accountId = $bank->account_id;
            $sourceLabel = "الحساب البنكي ({$bank->bank_name} - {$bank->account_number})";
        }

        if (! $accountId) {
            throw new Exception('لا يوجد حساب مالي مرتبط بالخزينة أو البنك المختار.');
        }

        // قفل سجل الحساب أثناء العملية لمنع التضارب المتزامن في الصرف
        $account = Account::whereKey($accountId)->lockForUpdate()->firstOrFail();
        $availableBalance = round($account->getCurrentBalance() + $creditBackAmount, 4);

        if ($amount > ($availableBalance + 0.0001)) {
            $formattedBalance = number_format($availableBalance, 2);
            $formattedAmount = number_format($amount, 2);
            throw new Exception("رصيد {$sourceLabel} غير كافٍ لإتمام عملية الصرف. الرصيد المتاح: {$formattedBalance}، والمبلغ المطلوب صرفه: {$formattedAmount}.");
        }
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
                    'updated_by'       => Auth::id() ?? $voucher->created_by,
                ]);
            }
        }
    }

    /**
     * عكس تسوية المستندات والفواتير المرتبطة بسطور السند عند التعديل أو الحذف
     */
    protected function reverseReferencedDocuments(Voucher $voucher): void
    {
        $voucher->loadMissing('details.reference');

        foreach ($voucher->details as $detail) {
            if (! $detail->reference_type || ! $detail->reference_id) {
                continue;
            }

            $reference = $detail->reference;

            if ($reference instanceof PurchaseBill) {
                $reversedAmount = (float) $detail->amount;
                $newPaidAmount = max(0.0, round((float) $reference->paid_amount - $reversedAmount, 4));
                $newRemainingAmount = max(0.0, round((float) $reference->total_amount - $newPaidAmount, 4));

                $updatePayload = [
                    'paid_amount'      => $newPaidAmount,
                    'remaining_amount' => $newRemainingAmount,
                    'updated_by'       => Auth::id() ?? $voucher->created_by,
                ];

                if ($newRemainingAmount <= 0.0001) {
                    $updatePayload['status'] = BillStatus::PAID;
                } elseif ($newPaidAmount > 0.0001) {
                    $updatePayload['status'] = BillStatus::PARTIALLY_PAID;
                }

                $reference->update($updatePayload);
            }
        }
    }
}