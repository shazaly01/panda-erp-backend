<?php

declare(strict_types=1);

namespace App\Modules\Sales\Services;

use App\Modules\Accounting\DTO\JournalEntryDetailDto;
use App\Modules\Accounting\DTO\JournalEntryDto;
use App\Modules\Accounting\Enums\EntrySource;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\AccountMappingService;
use App\Modules\Accounting\Services\JournalEntryService;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesReturn;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SalesAccountingService
{
    public function __construct(
        protected JournalEntryService $journalEntryService,
        protected AccountMappingService $accountMappingService
    ) {}

    public function createInvoiceJournalEntry(SalesInvoice $invoice): ?JournalEntry
    {
        $invoice->loadMissing(['items', 'partner']);

        $receivableAccountId = $this->resolveAccountId('sales_receivable', '11201');
        $revenueAccountId = $this->resolveAccountId('sales_revenue', '4101');
        $taxVatOutputAccountId = $this->resolveAccountId('tax_vat_output', '21201');
        $discountAllowedAccountId = $this->resolveAccountId('sales_discount_allowed', '4103');
        $cogsAccountId = $this->resolveAccountId('cost_of_goods_sold', '5101');
        $inventoryAssetAccountId = $this->resolveAccountId('inventory_asset', '11301');

        $details = [];

        // 1. الطرف المدين: استحقاق العميل (الذمم المدينة) بإجمالي الفاتورة
        $totalAmount = round((float) $invoice->total_amount, 4);
        if ($totalAmount > 0) {
            $customerName = $invoice->partner?->name ?? 'عميل نقدي';
            $details[] = new JournalEntryDetailDto(
                account_id: $receivableAccountId,
                debit: $totalAmount,
                credit: 0.0,
                description: "استحقاق مبيعات للعميل ({$customerName}) - فاتورة رقم: {$invoice->invoice_number}"
            );
        }

        // 2. الطرف المدين: الخصم المسموح به للعميل إن وجد
        $discountAmount = round((float) $invoice->discount_amount, 4);
        if ($discountAmount > 0) {
            $details[] = new JournalEntryDetailDto(
                account_id: $discountAllowedAccountId,
                debit: $discountAmount,
                credit: 0.0,
                description: "خصم مسموح به - فاتورة مبيعات رقم: {$invoice->invoice_number}"
            );
        }

        // 3. الطرف الدائن: إيرادات المبيعات بقيمة المجموع قبل الخصم والضريبة
        $subtotal = round((float) $invoice->subtotal, 4);
        if ($subtotal > 0) {
            $details[] = new JournalEntryDetailDto(
                account_id: $revenueAccountId,
                debit: 0.0,
                credit: $subtotal,
                description: "إيراد مبيعات - فاتورة رقم: {$invoice->invoice_number}"
            );
        }

        // 4. الطرف الدائن: ضريبة القيمة المضافة للمخرجات (المبيعات)
        $vatAmount = round((float) $invoice->vat_amount, 4);
        if ($vatAmount > 0) {
            $details[] = new JournalEntryDetailDto(
                account_id: $taxVatOutputAccountId,
                debit: 0.0,
                credit: $vatAmount,
                description: "ضريبة القيمة المضافة للمخرجات - فاتورة رقم: {$invoice->invoice_number}"
            );
        }

        // 5. الطرف الدائن: رسوم الخدمة ورسوم التوصيل إن وجدت
        $serviceCharge = round((float) $invoice->service_charge, 4);
        if ($serviceCharge > 0) {
            $details[] = new JournalEntryDetailDto(
                account_id: $revenueAccountId,
                debit: 0.0,
                credit: $serviceCharge,
                description: "إيراد رسوم خدمة - فاتورة رقم: {$invoice->invoice_number}"
            );
        }

        $deliveryFee = round((float) $invoice->delivery_fee, 4);
        if ($deliveryFee > 0) {
            $details[] = new JournalEntryDetailDto(
                account_id: $revenueAccountId,
                debit: 0.0,
                credit: $deliveryFee,
                description: "إيراد رسوم توصيل - فاتورة رقم: {$invoice->invoice_number}"
            );
        }

        // 6. قيد تكلفة البضاعة المباعة والمخزون (الجرد المستمر)
        $totalCost = 0.0;
        foreach ($invoice->items as $item) {
            $totalCost += ((float) $item->cost_price * (float) $item->quantity);
        }
        $totalCost = round($totalCost, 4);

        if ($totalCost > 0) {
            // مدين: تكلفة البضاعة المباعة COGS
            $details[] = new JournalEntryDetailDto(
                account_id: $cogsAccountId,
                debit: $totalCost,
                credit: 0.0,
                description: "تكلفة البضاعة المباعة (COGS) - فاتورة رقم: {$invoice->invoice_number}"
            );

            // دائن: خروج البضاعة من أصل المخزون
            $details[] = new JournalEntryDetailDto(
                account_id: $inventoryAssetAccountId,
                debit: 0.0,
                credit: $totalCost,
                description: "صرف بضاعة مباعة من المخزون - فاتورة رقم: {$invoice->invoice_number}"
            );
        }

        if (empty($details)) {
            return null;
        }

        return DB::transaction(function () use ($invoice, $details): JournalEntry {
            $entryDto = new JournalEntryDto(
                date: $invoice->invoice_date?->format('Y-m-d') ?? now()->toDateString(),
                details: $details,
                description: "قيد إثبات فاتورة مبيعات رقم: {$invoice->invoice_number}",
                currency_id: (int) $invoice->currency_id,
                source: EntrySource::Sales->value,
                reference_type: SalesInvoice::class,
                reference_id: (int) $invoice->id
            );

            $entry = $this->journalEntryService->createEntry($entryDto);

            return $this->journalEntryService->postEntry($entry);
        });
    }

    public function createInvoicePaymentJournalEntry(
        SalesInvoice $invoice,
        float $amount,
        int $paymentAccountId,
        ?string $referenceNumber = null,
        ?string $notes = null
    ): ?JournalEntry {
        $amount = round($amount, 4);
        if ($amount <= 0.0) {
            return null;
        }

        $invoice->loadMissing('partner');
        $receivableAccountId = $this->resolveAccountId('sales_receivable', '11201');
        $customerName = $invoice->partner?->name ?? 'العميل';

        $details = [
            // الطرف المدين: دخول النقدية بالصندوق أو الحساب البنكي
            new JournalEntryDetailDto(
                account_id: $paymentAccountId,
                debit: $amount,
                credit: 0.0,
                description: $notes ?: "تحصيل دفعة مبيعات - فاتورة رقم: {$invoice->invoice_number}" . ($referenceNumber ? " (مرجع: {$referenceNumber})" : '')
            ),
            // الطرف الدائن: تخفيض حساب العميل (الذمم المدينة)
            new JournalEntryDetailDto(
                account_id: $receivableAccountId,
                debit: 0.0,
                credit: $amount,
                description: $notes ?: "سداد وتحصيل من العميل ({$customerName}) - فاتورة رقم: {$invoice->invoice_number}"
            ),
        ];

        return DB::transaction(function () use ($invoice, $details, $referenceNumber): JournalEntry {
            $entryDto = new JournalEntryDto(
                date: now()->toDateString(),
                details: $details,
                description: "قيد تحصيل دفعة على فاتورة مبيعات رقم: {$invoice->invoice_number}" . ($referenceNumber ? " (مرجع: {$referenceNumber})" : ''),
                currency_id: (int) $invoice->currency_id,
                source: EntrySource::Sales->value,
                reference_type: SalesInvoice::class,
                reference_id: (int) $invoice->id
            );

            $entry = $this->journalEntryService->createEntry($entryDto);

            return $this->journalEntryService->postEntry($entry);
        });
    }

    public function deleteInvoiceJournalEntries(SalesInvoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            $entries = JournalEntry::where('reference_type', SalesInvoice::class)
                ->where('reference_id', $invoice->id)
                ->get();

            foreach ($entries as $entry) {
                $entry->details()->delete();
                $entry->forceDelete();
            }
        });
    }

    public function createReturnJournalEntry(SalesReturn $return): ?JournalEntry
    {
        $return->loadMissing(['items', 'partner']);

        $receivableAccountId = $this->resolveAccountId('sales_receivable', '11201');
        $returnSalesAccountId = $this->resolveAccountId('sales_returns', '4102');
        $taxVatOutputAccountId = $this->resolveAccountId('tax_vat_output', '21201');
        $discountAllowedAccountId = $this->resolveAccountId('sales_discount_allowed', '4103');
        $cogsAccountId = $this->resolveAccountId('cost_of_goods_sold', '5101');
        $inventoryAssetAccountId = $this->resolveAccountId('inventory_asset', '11301');

        $details = [];

        // 1. الطرف المدين: مردودات ومسموحات المبيعات
        $subtotal = round((float) $return->subtotal, 4);
        if ($subtotal > 0) {
            $details[] = new JournalEntryDetailDto(
                account_id: $returnSalesAccountId,
                debit: $subtotal,
                credit: 0.0,
                description: "إثبات مردودات مبيعات - إشعار دائن رقم: {$return->return_number}"
            );
        }

        // 2. الطرف المدين: عكس ضريبة القيمة المضافة للمخرجات
        $vatAmount = round((float) $return->vat_amount, 4);
        if ($vatAmount > 0) {
            $details[] = new JournalEntryDetailDto(
                account_id: $taxVatOutputAccountId,
                debit: $vatAmount,
                credit: 0.0,
                description: "عكس ضريبة مخرجات لمردود مبيعات رقم: {$return->return_number}"
            );
        }

        // 3. الطرف الدائن: عكس الخصم المسموح به الممنوح سابقاً إن وجد
        $discountAmount = round((float) $return->discount_amount, 4);
        if ($discountAmount > 0) {
            $details[] = new JournalEntryDetailDto(
                account_id: $discountAllowedAccountId,
                debit: 0.0,
                credit: $discountAmount,
                description: "عكس خصم مسموح به لمردود مبيعات رقم: {$return->return_number}"
            );
        }

        // 4. الطرف الدائن: تخفيض مستحقات العميل بإجمالي قيمة المردود
        $totalAmount = round((float) $return->total_amount, 4);
        if ($totalAmount > 0) {
            $customerName = $return->partner?->name ?? 'العميل';
            $details[] = new JournalEntryDetailDto(
                account_id: $receivableAccountId,
                debit: 0.0,
                credit: $totalAmount,
                description: "تخفيض مستحقات العميل ({$customerName}) بموجب مردود مبيعات رقم: {$return->return_number}"
            );
        }

        // 5. عكس قيد تكلفة البضاعة المباعة وإعادة تقييم المخزون للأصناف السليمة (item_condition = good)
        $totalCost = 0.0;
        foreach ($return->items as $item) {
            if ($item->item_condition === 'good') {
                $totalCost += ((float) $item->cost_price * (float) $item->quantity);
            }
        }
        $totalCost = round($totalCost, 4);

        if ($totalCost > 0) {
            // مدين: إعادة إدخال البضاعة لأصل المخزون
            $details[] = new JournalEntryDetailDto(
                account_id: $inventoryAssetAccountId,
                debit: $totalCost,
                credit: 0.0,
                description: "إعادة إدخال بضاعة مرتجعة سليمة للمخزون - مردود رقم: {$return->return_number}"
            );

            // دائن: عكس تكلفة البضاعة المباعة COGS
            $details[] = new JournalEntryDetailDto(
                account_id: $cogsAccountId,
                debit: 0.0,
                credit: $totalCost,
                description: "عكس تكلفة بضاعة مباعة لمردود مبيعات رقم: {$return->return_number}"
            );
        }

        if (empty($details)) {
            return null;
        }

        return DB::transaction(function () use ($return, $details): JournalEntry {
            $entryDto = new JournalEntryDto(
                date: $return->return_date?->format('Y-m-d') ?? now()->toDateString(),
                details: $details,
                description: "قيد إثبات مردود مبيعات رقم: {$return->return_number}",
                currency_id: (int) $return->currency_id,
                source: EntrySource::Sales->value,
                reference_type: SalesReturn::class,
                reference_id: (int) $return->id
            );

            $entry = $this->journalEntryService->createEntry($entryDto);

            return $this->journalEntryService->postEntry($entry);
        });
    }

    public function createReturnPaymentJournalEntry(
        SalesReturn $return,
        float $amount,
        int $paymentAccountId,
        ?string $referenceNumber = null,
        ?string $notes = null
    ): ?JournalEntry {
        $amount = round($amount, 4);
        if ($amount <= 0.0) {
            return null;
        }

        $return->loadMissing('partner');
        $receivableAccountId = $this->resolveAccountId('sales_receivable', '11201');
        $customerName = $return->partner?->name ?? 'العميل';

        $details = [
            // الطرف المدين: استعادة التزام الذمة المدينة للعميل بمبلغ الرد النقدي
            new JournalEntryDetailDto(
                account_id: $receivableAccountId,
                debit: $amount,
                credit: 0.0,
                description: $notes ?: "استرداد وصرف نقدي للعميل ({$customerName}) بموجب مردود رقم: {$return->return_number}"
            ),
            // الطرف الدائن: خروج السيولة من الخزينة أو البنك
            new JournalEntryDetailDto(
                account_id: $paymentAccountId,
                debit: 0.0,
                credit: $amount,
                description: $notes ?: "صرف مبلغ مردود مبيعات - إشعار رقم: {$return->return_number}" . ($referenceNumber ? " (مرجع: {$referenceNumber})" : '')
            ),
        ];

        return DB::transaction(function () use ($return, $details, $referenceNumber): JournalEntry {
            $entryDto = new JournalEntryDto(
                date: now()->toDateString(),
                details: $details,
                description: "قيد رد وصرف مبلغ مردود مبيعات رقم: {$return->return_number}" . ($referenceNumber ? " (مرجع: {$referenceNumber})" : ''),
                currency_id: (int) $return->currency_id,
                source: EntrySource::Sales->value,
                reference_type: SalesReturn::class,
                reference_id: (int) $return->id
            );

            $entry = $this->journalEntryService->createEntry($entryDto);

            return $this->journalEntryService->postEntry($entry);
        });
    }

    public function deleteReturnJournalEntries(SalesReturn $return): void
    {
        DB::transaction(function () use ($return): void {
            $entries = JournalEntry::where('reference_type', SalesReturn::class)
                ->where('reference_id', $return->id)
                ->get();

            foreach ($entries as $entry) {
                $entry->details()->delete();
                $entry->forceDelete();
            }
        });
    }

    protected function resolveAccountId(string $mappingKey, string $defaultCode): int
    {
        $accountId = $this->accountMappingService->getAccountId($mappingKey);
        if ($accountId) {
            return (int) $accountId;
        }

        $fallbackId = Account::where('code', $defaultCode)->value('id');
        if ($fallbackId) {
            return (int) $fallbackId;
        }

        throw new RuntimeException("تعذر العثور على الحساب المالي للمفتاح: [{$mappingKey}] أو الكود المحاسبي الافتراضي: [{$defaultCode}].");
    }
}