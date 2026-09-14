<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Services;

use App\Modules\Accounting\DTO\JournalEntryDetailDto;
use App\Modules\Accounting\DTO\JournalEntryDto;
use App\Modules\Accounting\Enums\EntrySource;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\AccountMappingService;
use App\Modules\Accounting\Services\JournalEntryService;
use App\Modules\Purchasing\Models\PurchaseBill;
use App\Modules\Purchasing\Models\PurchaseReturn;
use Illuminate\Support\Facades\DB;

class PurchasingAccountingService
{
    public function __construct(
        protected JournalEntryService $journalEntryService,
        protected AccountMappingService $accountMappingService
    ) {}

    public function createBillJournalEntry(PurchaseBill $bill): ?JournalEntry
    {
        $bill->loadMissing(['items', 'supplier']);

        $supplierPayableAccountId = $this->accountMappingService->getAccountId('purchases_payable');
        $taxVatInputAccountId = $this->accountMappingService->getAccountId('tax_vat_input');
        $discountReceivedAccountId = $this->accountMappingService->getAccountId('purchases_discount_received');
        $inventoryAssetAccountId = $this->accountMappingService->getAccountId('inventory_asset');
        $grniAccountId = $this->accountMappingService->getAccountId('inventory_grni');
        $purchasesExpenseAccountId = $this->accountMappingService->getAccountId('purchases_expense');
        $landedCostsAccountId = $this->accountMappingService->getAccountId('inventory_landed_costs');

        $accountDebits = [];

        foreach ($bill->items as $item) {
            $accountId = $item->account_id;

            if (! $accountId) {
                if ($bill->receipt_id) {
                    // دورة استلام مسبقة: إقفال وسيط البضاعة المستلمة غير المفوترة
                    $accountId = $grniAccountId ?: $purchasesExpenseAccountId;
                } elseif ($bill->warehouse_id) {
                    // شراء مباشر للمستودع بضغطة زر: توجيه مباشر لحساب أصل المخزون
                    $accountId = $inventoryAssetAccountId ?: $purchasesExpenseAccountId;
                } else {
                    // شراء خدمات أو مصروفات عامة
                    $accountId = $purchasesExpenseAccountId;
                }
            }

            if ($accountId) {
                $accountDebits[$accountId] = ($accountDebits[$accountId] ?? 0.0) + (float) $item->subtotal;
            }
        }

        $details = [];

        // 1. الجانب المدين: تكلفة المخزون أو وسيط البضاعة المستلمة أو المصروف
        foreach ($accountDebits as $accountId => $amount) {
            $amount = round($amount, 4);
            if ($amount > 0) {
                $details[] = new JournalEntryDetailDto(
                    account_id: (int) $accountId,
                    debit: $amount,
                    credit: 0.0,
                    description: "إثبات مشتريات / بضاعة - فاتورة رقم: {$bill->bill_number}"
                );
            }
        }

        // 2. الجانب المدين: ضريبة القيمة المضافة للمدخلات
        $taxAmount = round((float) $bill->tax_amount, 4);
        if ($taxAmount > 0) {
            $details[] = new JournalEntryDetailDto(
                account_id: $taxVatInputAccountId,
                debit: $taxAmount,
                credit: 0.0,
                description: "ضريبة القيمة المضافة للمدخلات - فاتورة شراء رقم: {$bill->bill_number}"
            );
        }

        // 3. الجانب المدين: تكاليف الشحن والنقل إن وجدت
        $shippingCost = round((float) $bill->shipping_cost, 4);
        if ($shippingCost > 0) {
            $shippingAccountId = $landedCostsAccountId ?: $purchasesExpenseAccountId;
            $details[] = new JournalEntryDetailDto(
                account_id: $shippingAccountId,
                debit: $shippingCost,
                credit: 0.0,
                description: "تكاليف شحن ونقل مشتريات - فاتورة رقم: {$bill->bill_number}"
            );
        }

        // 4. الجانب الدائن: الخصم المكتسب من المورد إن وجد
        $discountAmount = round((float) $bill->discount_amount, 4);
        if ($discountAmount > 0) {
            $details[] = new JournalEntryDetailDto(
                account_id: $discountReceivedAccountId,
                debit: 0.0,
                credit: $discountAmount,
                description: "خصم مكتسب على المشتريات - فاتورة رقم: {$bill->bill_number}"
            );
        }

        // 5. الجانب الدائن: التزام المورد (الذمم الدائنة) بصافي المبلغ الإجمالي للفاتورة
        $totalAmount = round((float) $bill->total_amount, 4);
        if ($totalAmount > 0) {
            $details[] = new JournalEntryDetailDto(
                account_id: $supplierPayableAccountId,
                debit: 0.0,
                credit: $totalAmount,
                description: "استحقاق المورد ({$bill->supplier?->name}) - فاتورة رقم: {$bill->bill_number}"
            );
        }

        if (empty($details)) {
            return null;
        }

        return DB::transaction(function () use ($bill, $details): JournalEntry {
            $entryDto = new JournalEntryDto(
                date: $bill->bill_date?->format('Y-m-d') ?? now()->toDateString(),
                details: $details,
                description: "قيد إثبات فاتورة شراء رقم: {$bill->bill_number}",
                currency_id: (int) $bill->currency_id,
                source: EntrySource::Purchases->value,
                reference_type: PurchaseBill::class,
                reference_id: (int) $bill->id
            );

            $entry = $this->journalEntryService->createEntry($entryDto);

            return $this->journalEntryService->postEntry($entry);
        });
    }

    public function createBillPaymentJournalEntry(
        PurchaseBill $bill,
        float $amount,
        int $paymentAccountId,
        ?string $referenceNumber = null,
        ?string $notes = null
    ): ?JournalEntry {
        $amount = round($amount, 4);
        if ($amount <= 0.0) {
            return null;
        }

        $bill->loadMissing('supplier');
        $supplierPayableAccountId = $this->accountMappingService->getAccountId('purchases_payable');

        $details = [
            // الجانب المدين: تخفيض التزام المورد (الذمم الدائنة)
            new JournalEntryDetailDto(
                account_id: (int) $supplierPayableAccountId,
                debit: $amount,
                credit: 0.0,
                description: $notes ?: "سداد دفعة للمورد ({$bill->supplier?->name}) - فاتورة رقم: {$bill->bill_number}"
            ),
            // الجانب الدائن: خروج السيولة من حساب الصندوق أو البنك
            new JournalEntryDetailDto(
                account_id: $paymentAccountId,
                debit: 0.0,
                credit: $amount,
                description: $notes ?: "سداد دفعة من فاتورة رقم: {$bill->bill_number}" . ($referenceNumber ? " - مرجع: {$referenceNumber}" : '')
            ),
        ];

        return DB::transaction(function () use ($bill, $details, $referenceNumber): JournalEntry {
            $entryDto = new JournalEntryDto(
                date: now()->toDateString(),
                details: $details,
                description: "قيد سداد دفعة على فاتورة شراء رقم: {$bill->bill_number}" . ($referenceNumber ? " (مرجع: {$referenceNumber})" : ''),
                currency_id: (int) $bill->currency_id,
                source: EntrySource::Purchases->value,
                reference_type: PurchaseBill::class,
                reference_id: (int) $bill->id
            );

            $entry = $this->journalEntryService->createEntry($entryDto);

            return $this->journalEntryService->postEntry($entry);
        });
    }

    public function deleteBillJournalEntries(PurchaseBill $bill): void
    {
        DB::transaction(function () use ($bill): void {
            $entries = JournalEntry::where('reference_type', PurchaseBill::class)
                ->where('reference_id', $bill->id)
                ->get();

            foreach ($entries as $entry) {
                $entry->details()->delete();
                $entry->forceDelete();
            }
        });
    }

    public function createReturnJournalEntry(PurchaseReturn $return): ?JournalEntry
    {
        $return->loadMissing(['items.billItem', 'supplier', 'bill']);

        $supplierPayableAccountId = $this->accountMappingService->getAccountId('purchases_payable');
        $taxVatInputAccountId = $this->accountMappingService->getAccountId('tax_vat_input');
        $inventoryAssetAccountId = $this->accountMappingService->getAccountId('inventory_asset');
        $grniAccountId = $this->accountMappingService->getAccountId('inventory_grni');
        $purchasesExpenseAccountId = $this->accountMappingService->getAccountId('purchases_expense');

        $details = [];

        // 1. الطرف المدين: تخفيض التزام المورد (الذمم الدائنة) بإجمالي قيمة المردود شاملاً الضريبة
        $totalAmount = round((float) $return->total_amount, 4);
        if ($totalAmount > 0) {
            $details[] = new JournalEntryDetailDto(
                account_id: $supplierPayableAccountId,
                debit: $totalAmount,
                credit: 0.0,
                description: "تخفيض التزام المورد ({$return->supplier?->name}) بموجب مردود مشتريات رقم: {$return->return_number}"
            );
        }

        // 2. الطرف الدائن: عكس تكلفة المشتريات أو أصل المخزون أو وسيط الاستلام لكل حساب
        $accountCredits = [];
        foreach ($return->items as $item) {
            $accountId = $item->billItem?->account_id;

            if (! $accountId) {
                if ($return->bill?->receipt_id) {
                    $accountId = $grniAccountId ?: $purchasesExpenseAccountId;
                } elseif ($return->warehouse_id || $return->bill?->warehouse_id) {
                    $accountId = $inventoryAssetAccountId ?: $purchasesExpenseAccountId;
                } else {
                    $accountId = $purchasesExpenseAccountId;
                }
            }

            if ($accountId) {
                $accountCredits[$accountId] = ($accountCredits[$accountId] ?? 0.0) + (float) $item->subtotal;
            }
        }

        foreach ($accountCredits as $accountId => $amount) {
            $amount = round($amount, 4);
            if ($amount > 0) {
                $details[] = new JournalEntryDetailDto(
                    account_id: (int) $accountId,
                    debit: 0.0,
                    credit: $amount,
                    description: "عكس تكلفة بضاعة مرتجعة - مستند مردود رقم: {$return->return_number}"
                );
            }
        }

        // 3. الطرف الدائن: عكس ضريبة القيمة المضافة للمدخلات
        $taxAmount = round((float) $return->tax_amount, 4);
        if ($taxAmount > 0) {
            $details[] = new JournalEntryDetailDto(
                account_id: $taxVatInputAccountId,
                debit: 0.0,
                credit: $taxAmount,
                description: "عكس ضريبة مدخلات لمردود مشتريات رقم: {$return->return_number}"
            );
        }

        if (empty($details)) {
            return null;
        }

        return DB::transaction(function () use ($return, $details): JournalEntry {
            $entryDto = new JournalEntryDto(
                date: $return->return_date?->format('Y-m-d') ?? now()->toDateString(),
                details: $details,
                description: "قيد إثبات مردود مشتريات رقم: {$return->return_number}",
                currency_id: (int) $return->currency_id,
                source: EntrySource::Purchases->value,
                reference_type: PurchaseReturn::class,
                reference_id: (int) $return->id
            );

            $entry = $this->journalEntryService->createEntry($entryDto);

            return $this->journalEntryService->postEntry($entry);
        });
    }

    public function deleteReturnJournalEntries(PurchaseReturn $return): void
    {
        DB::transaction(function () use ($return): void {
            $entries = JournalEntry::where('reference_type', PurchaseReturn::class)
                ->where('reference_id', $return->id)
                ->get();

            foreach ($entries as $entry) {
                $entry->details()->delete();
                $entry->forceDelete();
            }
        });
    }
}