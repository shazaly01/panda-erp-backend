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
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

class PurchasingAccountingService
{
    public function __construct(
        protected JournalEntryService $journalEntryService,
        protected AccountMappingService $accountMappingService
    ) {}

    public function createBillJournalEntry(PurchaseBill $bill): ?JournalEntry
    {
        $details = $this->buildBillJournalEntryDetails($bill);

        if (empty($details)) {
            return null;
        }

        return DB::transaction(function () use ($bill, $details): JournalEntry {
            $date = $bill->bill_date instanceof DateTimeInterface
                ? $bill->bill_date->format('Y-m-d')
                : ($bill->bill_date ? (string) $bill->bill_date : now()->toDateString());

            $entryDto = new JournalEntryDto(
                date: $date,
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

    public function updateBillJournalEntry(PurchaseBill $bill): ?JournalEntry
    {
        $details = $this->buildBillJournalEntryDetails($bill);

        if (empty($details)) {
            return null;
        }

        return DB::transaction(function () use ($bill, $details): ?JournalEntry {
            /** @var JournalEntry|null $entry */
            $entry = JournalEntry::where('reference_type', PurchaseBill::class)
                ->where('reference_id', $bill->id)
                ->first();

            if (! $entry) {
                return $this->createBillJournalEntry($bill);
            }

            $date = $bill->bill_date instanceof DateTimeInterface
                ? $bill->bill_date->format('Y-m-d')
                : ($bill->bill_date ? (string) $bill->bill_date : now()->toDateString());

            $totalDebit = round((float) array_sum(array_map(fn (JournalEntryDetailDto $d): float => $d->debit, $details)), 4);
            $totalCredit = round((float) array_sum(array_map(fn (JournalEntryDetailDto $d): float => $d->credit, $details)), 4);

            $updateData = [
                'date' => $date,
                'description' => "قيد إثبات فاتورة شراء رقم: {$bill->bill_number}",
                'currency_id' => (int) $bill->currency_id,
            ];

            if ($entry->isFillable('total_debit') || array_key_exists('total_debit', $entry->getAttributes())) {
                $updateData['total_debit'] = $totalDebit;
            }
            if ($entry->isFillable('total_credit') || array_key_exists('total_credit', $entry->getAttributes())) {
                $updateData['total_credit'] = $totalCredit;
            }

            $entry->update($updateData);

            $entry->details()->delete();

            foreach ($details as $detail) {
                $entry->details()->create([
                    'account_id' => $detail->account_id,
                    'debit' => $detail->debit,
                    'credit' => $detail->credit,
                    'description' => $detail->description,
                ]);
            }

            return $entry->load('details');
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
            new JournalEntryDetailDto(
                account_id: (int) $supplierPayableAccountId,
                debit: $amount,
                credit: 0.0,
                description: $notes ?: "سداد دفعة للمورد ({$bill->supplier?->name}) - فاتورة رقم: {$bill->bill_number}"
            ),
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
        $details = $this->buildReturnJournalEntryDetails($return);

        if (empty($details)) {
            return null;
        }

        return DB::transaction(function () use ($return, $details): JournalEntry {
            $date = $return->return_date instanceof DateTimeInterface
                ? $return->return_date->format('Y-m-d')
                : ($return->return_date ? (string) $return->return_date : now()->toDateString());

            $entryDto = new JournalEntryDto(
                date: $date,
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

    public function updateReturnJournalEntry(PurchaseReturn $return): ?JournalEntry
    {
        $details = $this->buildReturnJournalEntryDetails($return);

        if (empty($details)) {
            return null;
        }

        return DB::transaction(function () use ($return, $details): ?JournalEntry {
            /** @var JournalEntry|null $entry */
            $entry = JournalEntry::where('reference_type', PurchaseReturn::class)
                ->where('reference_id', $return->id)
                ->first();

            if (! $entry) {
                return $this->createReturnJournalEntry($return);
            }

            $date = $return->return_date instanceof DateTimeInterface
                ? $return->return_date->format('Y-m-d')
                : ($return->return_date ? (string) $return->return_date : now()->toDateString());

            $totalDebit = round((float) array_sum(array_map(fn (JournalEntryDetailDto $d): float => $d->debit, $details)), 4);
            $totalCredit = round((float) array_sum(array_map(fn (JournalEntryDetailDto $d): float => $d->credit, $details)), 4);

            $updateData = [
                'date' => $date,
                'description' => "قيد إثبات مردود مشتريات رقم: {$return->return_number}",
                'currency_id' => (int) $return->currency_id,
            ];

            if ($entry->isFillable('total_debit') || array_key_exists('total_debit', $entry->getAttributes())) {
                $updateData['total_debit'] = $totalDebit;
            }
            if ($entry->isFillable('total_credit') || array_key_exists('total_credit', $entry->getAttributes())) {
                $updateData['total_credit'] = $totalCredit;
            }

            $entry->update($updateData);

            $entry->details()->delete();

            foreach ($details as $detail) {
                $entry->details()->create([
                    'account_id' => $detail->account_id,
                    'debit' => $detail->debit,
                    'credit' => $detail->credit,
                    'description' => $detail->description,
                ]);
            }

            return $entry->load('details');
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

    protected function buildBillJournalEntryDetails(PurchaseBill $bill): array
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
                    $accountId = $grniAccountId ?: $purchasesExpenseAccountId;
                } elseif ($bill->warehouse_id) {
                    $accountId = $inventoryAssetAccountId ?: $purchasesExpenseAccountId;
                } else {
                    $accountId = $purchasesExpenseAccountId;
                }
            }

            if ($accountId) {
                $accountDebits[$accountId] = ($accountDebits[$accountId] ?? 0.0) + (float) $item->subtotal;
            }
        }

        $details = [];

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

        $taxAmount = round((float) $bill->tax_amount, 4);
        if ($taxAmount > 0) {
            $details[] = new JournalEntryDetailDto(
                account_id: $taxVatInputAccountId,
                debit: $taxAmount,
                credit: 0.0,
                description: "ضريبة القيمة المضافة للمدخلات - فاتورة شراء رقم: {$bill->bill_number}"
            );
        }

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

        $discountAmount = round((float) $bill->discount_amount, 4);
        if ($discountAmount > 0) {
            $details[] = new JournalEntryDetailDto(
                account_id: $discountReceivedAccountId,
                debit: 0.0,
                credit: $discountAmount,
                description: "خصم مكتسب على المشتريات - فاتورة رقم: {$bill->bill_number}"
            );
        }

        $totalAmount = round((float) $bill->total_amount, 4);
        if ($totalAmount > 0) {
            $details[] = new JournalEntryDetailDto(
                account_id: $supplierPayableAccountId,
                debit: 0.0,
                credit: $totalAmount,
                description: "استحقاق المورد ({$bill->supplier?->name}) - فاتورة رقم: {$bill->bill_number}"
            );
        }

        return $details;
    }

    protected function buildReturnJournalEntryDetails(PurchaseReturn $return): array
    {
        $return->loadMissing(['items.billItem', 'supplier', 'bill']);

        $supplierPayableAccountId = $this->accountMappingService->getAccountId('purchases_payable');
        $taxVatInputAccountId = $this->accountMappingService->getAccountId('tax_vat_input');
        $inventoryAssetAccountId = $this->accountMappingService->getAccountId('inventory_asset');
        $grniAccountId = $this->accountMappingService->getAccountId('inventory_grni');
        $purchasesExpenseAccountId = $this->accountMappingService->getAccountId('purchases_expense');

        $details = [];

        $totalAmount = round((float) $return->total_amount, 4);
        if ($totalAmount > 0) {
            $details[] = new JournalEntryDetailDto(
                account_id: $supplierPayableAccountId,
                debit: $totalAmount,
                credit: 0.0,
                description: "تخفيض التزام المورد ({$return->supplier?->name}) بموجب مردود مشتريات رقم: {$return->return_number}"
            );
        }

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

        $taxAmount = round((float) $return->tax_amount, 4);
        if ($taxAmount > 0) {
            $details[] = new JournalEntryDetailDto(
                account_id: $taxVatInputAccountId,
                debit: 0.0,
                credit: $taxAmount,
                description: "عكس ضريبة مدخلات لمردود مشتريات رقم: {$return->return_number}"
            );
        }

        return $details;
    }
}