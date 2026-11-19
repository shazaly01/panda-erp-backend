<?php

declare(strict_types=1);

namespace App\Modules\Sales\Services;

use App\Models\User;
use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Models\Box;
use App\Modules\Core\Services\SequenceService;
use App\Modules\Inventory\Models\Bom;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Models\ProductUnit;
use App\Modules\Inventory\Services\StockMovementService;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesInvoiceItem;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SalesInvoiceService
{
    public function __construct(
        protected SequenceService $sequenceService,
        protected StockMovementService $stockMovementService,
        protected SalesAccountingService $salesAccountingService
    ) {}

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = SalesInvoice::query()
            ->with([
                'partner.priceList',
                'warehouse',
                'costCenter',
                'currency',
                'shift',
                'creator',
                'payments.box',
                'payments.bankAccount',
            ])
            ->withCount(['items', 'payments', 'returns']);

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('reference_number', 'like', "%{$search}%")
                    ->orWhere('customer_name_text', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('partner', function (Builder $partnerQuery) use ($search): void {
                        $partnerQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (! empty($filters['posting_status'])) {
            $query->where('posting_status', $filters['posting_status']);
        }

        if (! empty($filters['partner_id'])) {
            $query->where('partner_id', $filters['partner_id']);
        }

        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (! empty($filters['shift_id'])) {
            $query->where('shift_id', $filters['shift_id']);
        }

        if (! empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        if (! empty($filters['order_type'])) {
            $query->where('order_type', $filters['order_type']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('invoice_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('invoice_date', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    public function find(int $id): SalesInvoice
    {
        return SalesInvoice::with([
            'partner.priceList',
            'warehouse',
            'costCenter',
            'currency',
            'shift',
            'creator',
            'updater',
            'items.product.units.unit',
            'items.product.units.prices.priceList',
            'items.product.units.barcodes',
            'items.product.stocks',
            'items.unit',
            'payments.box',
            'payments.bankAccount',
            'payments.creator',
            'returns',
        ])->findOrFail($id);
    }

    public function findByUuid(string $uuid): SalesInvoice
    {
        return SalesInvoice::with([
            'partner.priceList',
            'warehouse',
            'costCenter',
            'currency',
            'creator',
            'items.product',
            'items.unit',
            'payments.box',
            'payments.bankAccount',
        ])->where('uuid', $uuid)->firstOrFail();
    }

    /**
     * نقطة بيع الكاشير السريعة: إنشاء وتأكيد وترحيل الفاتورة ودفعاتها في خطوة واحدة
     */
    public function posCheckout(array $data, int $userId): SalesInvoice
    {
        $data['channel'] = 'pos';
        $data['auto_post'] = true;

        return $this->create($data, $userId);
    }

    public function create(array $data, int $userId): SalesInvoice
    {
        return DB::transaction(function () use ($data, $userId): SalesInvoice {
            $user = User::find($userId);

            $invoiceDate = ! empty($data['invoice_date'])
                ? Carbon::parse($data['invoice_date'])
                : Carbon::now();

            $branchId = isset($data['branch_id']) ? (int) $data['branch_id'] : null;
            $warehouseId = ! empty($data['warehouse_id'])
                ? (int) $data['warehouse_id']
                : (int) ($user?->default_warehouse_id ?? 0);

            if ($warehouseId <= 0) {
                throw new RuntimeException('يجب تحديد المستودع أو تعيين مستودع افتراضي للمستخدم.');
            }

            $costCenterId = array_key_exists('cost_center_id', $data) && $data['cost_center_id'] !== null
                ? (int) $data['cost_center_id']
                : ($user?->default_cost_center_id ? (int) $user->default_cost_center_id : null);

            $invoiceNumber = $this->sequenceService->generateNumber(
                'sales_invoice',
                $branchId,
                null,
                $invoiceDate->toDateString()
            );

            $calculatedData = $this->calculateInvoiceFinancials($data);

            $paidAmount = 0.0;
            $paymentsToCreate = [];

            if (! empty($data['payments']) && is_array($data['payments'])) {
                foreach ($data['payments'] as $paymentData) {
                    $amount = round((float) $paymentData['amount'], 2);
                    if ($amount > 0) {
                        $method = $paymentData['payment_method'];
                        $boxId = ! empty($paymentData['box_id'])
                            ? (int) $paymentData['box_id']
                            : ($method === 'cash' && $user?->default_box_id ? (int) $user->default_box_id : null);

                        $bankAccountId = ! empty($paymentData['bank_account_id'])
                            ? (int) $paymentData['bank_account_id']
                            : (in_array($method, ['card', 'bank_transfer'], true) && $user?->default_bank_account_id
                                ? (int) $user->default_bank_account_id
                                : null);

                        $paymentData['box_id'] = $boxId;
                        $paymentData['bank_account_id'] = $bankAccountId;

                        $paidAmount += $amount;
                        $paymentsToCreate[] = $paymentData;
                    }
                }
            }

            $totalAmount = $calculatedData['total_amount'];
            $remainingAmount = max(0.0, round($totalAmount - $paidAmount, 2));

            $paymentStatus = 'unpaid';
            if ($paidAmount >= $totalAmount && $totalAmount > 0) {
                $paymentStatus = 'paid';
            } elseif ($paidAmount > 0) {
                $paymentStatus = 'partially_paid';
            }

            $invoice = SalesInvoice::create([
                'invoice_number' => $invoiceNumber,
                'reference_number' => $data['reference_number'] ?? null,
                'invoice_date' => $invoiceDate,
                'due_date' => ! empty($data['due_date']) ? Carbon::parse($data['due_date'])->toDateString() : null,
                'partner_id' => $data['partner_id'],
                'customer_name_text' => $data['customer_name_text'] ?? null,
                'branch_id' => $branchId,
                'warehouse_id' => $warehouseId,
                'cost_center_id' => $costCenterId,
                'currency_id' => $data['currency_id'] ?? null,
                'exchange_rate' => $data['exchange_rate'] ?? 1.0000,
                'shift_id' => $data['shift_id'] ?? null,
                'channel' => $data['channel'] ?? 'standard',
                'order_type' => $data['order_type'] ?? 'standard',
                'table_label' => $data['table_label'] ?? null,
                'status' => 'draft',
                'payment_status' => $paymentStatus,
                'posting_status' => 'unposted',
                'posted_at' => null,
                'subtotal' => $calculatedData['subtotal'],
                'discount_type' => $calculatedData['discount_type'],
                'discount_value' => $calculatedData['discount_value'],
                'discount_amount' => $calculatedData['discount_amount'],
                'taxable_amount' => $calculatedData['taxable_amount'],
                'vat_amount' => $calculatedData['vat_amount'],
                'service_charge' => $calculatedData['service_charge'],
                'delivery_fee' => $calculatedData['delivery_fee'],
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remainingAmount,
                'notes' => $data['notes'] ?? null,
                'terms_conditions' => $data['terms_conditions'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            foreach ($calculatedData['items'] as $itemData) {
                $invoice->items()->create([
                    'product_id' => $itemData['product_id'],
                    'unit_id' => $itemData['unit_id'] ?? null,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'cost_price' => $itemData['cost_price'],
                    'subtotal' => $itemData['subtotal'],
                    'discount_type' => $itemData['discount_type'],
                    'discount_value' => $itemData['discount_value'],
                    'discount_amount' => $itemData['discount_amount'],
                    'tax_rate' => $itemData['tax_rate'],
                    'tax_amount' => $itemData['tax_amount'],
                    'total_amount' => $itemData['total_amount'],
                    'options' => $itemData['options'] ?? null,
                    'notes' => $itemData['notes'] ?? null,
                ]);
            }

            foreach ($paymentsToCreate as $pData) {
                $paymentNumber = $this->sequenceService->generateNumber(
                    'acc_receipt',
                    $branchId,
                    null,
                    $invoiceDate->toDateString()
                );

                $invoice->payments()->create([
                    'payment_number' => $paymentNumber,
                    'shift_id' => $invoice->shift_id,
                    'payment_method' => $pData['payment_method'],
                    'amount' => $pData['amount'],
                    'currency_id' => $invoice->currency_id,
                    'exchange_rate' => $invoice->exchange_rate,
                    'box_id' => $pData['box_id'] ?? null,
                    'bank_account_id' => $pData['bank_account_id'] ?? null,
                    'transaction_reference' => $pData['transaction_reference'] ?? null,
                    'payment_date' => ! empty($pData['payment_date']) ? Carbon::parse($pData['payment_date']) : Carbon::now(),
                    'notes' => $pData['notes'] ?? null,
                    'created_by' => $userId,
                ]);
            }

            if (! empty($data['auto_post'])) {
                $this->confirm($invoice, $userId);
                return $this->post($invoice->fresh(), $userId);
            }

            return $this->find($invoice->id);
        });
    }

    public function update(SalesInvoice $invoice, array $data, int $userId): SalesInvoice
    {
        if ($invoice->status === 'cancelled') {
            throw new RuntimeException('لا يمكن تعديل فاتورة ملغاة.');
        }

        if ($invoice->returns()->exists()) {
            throw new RuntimeException('لا يمكن تعديل فاتورة مرتبطة بمرتجعات قائمة.');
        }

        return DB::transaction(function () use ($invoice, $data, $userId): SalesInvoice {
            $wasPosted = ($invoice->posting_status === 'posted');

            if ($wasPosted) {
                $this->stockMovementService->clearDocumentMovements($invoice);
                $this->salesAccountingService->deleteInvoiceJournalEntries($invoice);

                $invoice->update([
                    'posting_status' => 'unposted',
                    'posted_at' => null,
                ]);
            }

            $itemsPayload = $data['items'] ?? $invoice->items()->get()->map(function (SalesInvoiceItem $item): array {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'unit_id' => $item->unit_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount_type' => $item->discount_type,
                    'discount_value' => $item->discount_value,
                    'tax_rate' => $item->tax_rate,
                    'options' => $item->options,
                    'notes' => $item->notes,
                ];
            })->toArray();

            $calculationInput = array_merge([
                'discount_type' => $invoice->discount_type,
                'discount_value' => $invoice->discount_value,
                'service_charge' => $invoice->service_charge,
                'delivery_fee' => $invoice->delivery_fee,
            ], $data, ['items' => $itemsPayload]);

            $calculatedData = $this->calculateInvoiceFinancials($calculationInput);
            $totalAmount = $calculatedData['total_amount'];

            if (array_key_exists('payments', $data)) {
                $this->syncInvoicePayments($invoice, $data['payments'] ?? [], $userId);
            }

            $existingPaymentsTotal = round((float) $invoice->payments()->sum('amount'), 2);
            $remainingAmount = max(0.0, round($totalAmount - $existingPaymentsTotal, 2));

            $paymentStatus = 'unpaid';
            if ($existingPaymentsTotal >= $totalAmount && $totalAmount > 0) {
                $paymentStatus = 'paid';
            } elseif ($existingPaymentsTotal > 0) {
                $paymentStatus = 'partially_paid';
            }

            $invoice->update([
                'invoice_date' => ! empty($data['invoice_date']) ? Carbon::parse($data['invoice_date']) : $invoice->invoice_date,
                'due_date' => array_key_exists('due_date', $data)
                    ? (! empty($data['due_date']) ? Carbon::parse($data['due_date'])->toDateString() : null)
                    : $invoice->due_date,
                'partner_id' => $data['partner_id'] ?? $invoice->partner_id,
                'customer_name_text' => array_key_exists('customer_name_text', $data)
                    ? $data['customer_name_text']
                    : $invoice->customer_name_text,
                'branch_id' => array_key_exists('branch_id', $data) ? $data['branch_id'] : $invoice->branch_id,
                'warehouse_id' => $data['warehouse_id'] ?? $invoice->warehouse_id,
                'cost_center_id' => array_key_exists('cost_center_id', $data) ? $data['cost_center_id'] : $invoice->cost_center_id,
                'currency_id' => array_key_exists('currency_id', $data) ? $data['currency_id'] : $invoice->currency_id,
                'exchange_rate' => $data['exchange_rate'] ?? $invoice->exchange_rate,
                'channel' => $data['channel'] ?? $invoice->channel,
                'order_type' => $data['order_type'] ?? $invoice->order_type,
                'table_label' => array_key_exists('table_label', $data) ? $data['table_label'] : $invoice->table_label,
                'subtotal' => $calculatedData['subtotal'],
                'discount_type' => $calculatedData['discount_type'],
                'discount_value' => $calculatedData['discount_value'],
                'discount_amount' => $calculatedData['discount_amount'],
                'taxable_amount' => $calculatedData['taxable_amount'],
                'vat_amount' => $calculatedData['vat_amount'],
                'service_charge' => $calculatedData['service_charge'],
                'delivery_fee' => $calculatedData['delivery_fee'],
                'total_amount' => $totalAmount,
                'paid_amount' => $existingPaymentsTotal,
                'remaining_amount' => $remainingAmount,
                'payment_status' => $paymentStatus,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $invoice->notes,
                'terms_conditions' => array_key_exists('terms_conditions', $data) ? $data['terms_conditions'] : $invoice->terms_conditions,
                'updated_by' => $userId,
            ]);

            if (array_key_exists('items', $data)) {
                $existingItems = $invoice->items()->get()->keyBy('id');
                $submittedItemIds = collect($calculatedData['items'])
                    ->pluck('id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->toArray();

                foreach ($existingItems as $existingId => $existingItem) {
                    if (! in_array($existingId, $submittedItemIds, true)) {
                        $existingItem->delete();
                    }
                }

                foreach ($calculatedData['items'] as $itemData) {
                    if (! empty($itemData['id']) && $existingItems->has($itemData['id'])) {
                        /** @var SalesInvoiceItem $existingItem */
                        $existingItem = $existingItems->get($itemData['id']);
                        $existingItem->update([
                            'product_id' => $itemData['product_id'],
                            'unit_id' => $itemData['unit_id'] ?? null,
                            'quantity' => $itemData['quantity'],
                            'unit_price' => $itemData['unit_price'],
                            'cost_price' => $itemData['cost_price'],
                            'subtotal' => $itemData['subtotal'],
                            'discount_type' => $itemData['discount_type'],
                            'discount_value' => $itemData['discount_value'],
                            'discount_amount' => $itemData['discount_amount'],
                            'tax_rate' => $itemData['tax_rate'],
                            'tax_amount' => $itemData['tax_amount'],
                            'total_amount' => $itemData['total_amount'],
                            'options' => $itemData['options'] ?? null,
                            'notes' => $itemData['notes'] ?? null,
                        ]);
                    } else {
                        $invoice->items()->create([
                            'product_id' => $itemData['product_id'],
                            'unit_id' => $itemData['unit_id'] ?? null,
                            'quantity' => $itemData['quantity'],
                            'unit_price' => $itemData['unit_price'],
                            'cost_price' => $itemData['cost_price'],
                            'subtotal' => $itemData['subtotal'],
                            'discount_type' => $itemData['discount_type'],
                            'discount_value' => $itemData['discount_value'],
                            'discount_amount' => $itemData['discount_amount'],
                            'tax_rate' => $itemData['tax_rate'],
                            'tax_amount' => $itemData['tax_amount'],
                            'total_amount' => $itemData['total_amount'],
                            'options' => $itemData['options'] ?? null,
                            'notes' => $itemData['notes'] ?? null,
                        ]);
                    }
                }
            }

            if ($wasPosted) {
                if ($invoice->status === 'draft') {
                    $invoice->update(['status' => 'confirmed']);
                }
                return $this->post($invoice->fresh(), $userId);
            }

            return $this->find($invoice->id);
        });
    }

    public function delete(SalesInvoice $invoice): bool
    {
        if ($invoice->returns()->exists()) {
            throw new RuntimeException('لا يمكن حذف فاتورة مرتبطة بمرتجعات قائمة.');
        }

        return DB::transaction(function () use ($invoice): bool {
            if ($invoice->posting_status === 'posted') {
                $this->stockMovementService->clearDocumentMovements($invoice);
                $this->salesAccountingService->deleteInvoiceJournalEntries($invoice);
            }

            $invoice->payments()->delete();
            $invoice->items()->delete();

            return (bool) $invoice->delete();
        });
    }

    public function confirm(SalesInvoice $invoice, int $userId): SalesInvoice
    {
        if ($invoice->status !== 'draft') {
            throw new RuntimeException('لا يمكن تأكيد الفاتورة إلا إذا كانت في حالة مسودة.');
        }

        if ($invoice->items()->count() === 0) {
            throw new RuntimeException('لا يمكن تأكيد فاتورة بدون بنود.');
        }

        $invoice->update([
            'status' => 'confirmed',
            'updated_by' => $userId,
        ]);

        return $this->find($invoice->id);
    }

    public function post(SalesInvoice $invoice, int $userId): SalesInvoice
    {
        if ($invoice->status !== 'confirmed') {
            throw new RuntimeException('يجب تأكيد الفاتورة أولاً قبل الترحيل.');
        }

        if ($invoice->posting_status === 'posted') {
            throw new RuntimeException('الفاتورة مرحلة بالفعل مسبقاً.');
        }

        return DB::transaction(function () use ($invoice, $userId): SalesInvoice {
            $invoice->loadMissing(['items.product', 'payments.box', 'payments.bankAccount']);

            // 1. فحص توفر الرصيد المخزني إذا كانت الرقابة المخزنية مفعلة للمستخدم
            $this->validateStockAvailability($invoice, $userId);

            // 2. توليد حركات خصم المخزون اللحظية لكل بند (مع تفكيك الأصناف التجميعية BOM)
            foreach ($invoice->items as $item) {
                $product = $item->product;
                if ($product && $product->type === 'service') {
                    continue;
                }

                $productUnit = $this->resolveProductUnit((int) $item->product_id, $item->unit_id ? (int) $item->unit_id : null);
                $conversionFactor = $productUnit && (float) $productUnit->conversion_factor > 0
                    ? (float) $productUnit->conversion_factor
                    : 1.0;
                $soldBaseQty = (float) $item->quantity * $conversionFactor;

                $bom = $this->getActiveProductBom((int) $item->product_id);
                if ($bom && $bom->items->isNotEmpty()) {
                    $bomItemsBreakdown = $this->decomposeBomItems($bom, $soldBaseQty);

                    foreach ($bomItemsBreakdown as $component) {
                        $this->stockMovementService->recordMovement(
                            productId: $component['product_id'],
                            warehouseId: (int) $invoice->warehouse_id,
                            productUnitId: $component['product_unit_id'],
                            movementType: 'sales',
                            quantity: $component['quantity'],
                            unitCost: $component['unit_cost'],
                            reference: $invoice,
                            notes: "خصم مكون صنف تجميعي ({$product?->name}) - فاتورة رقم: {$invoice->invoice_number}",
                            userId: $userId
                        );
                    }
                } else {
                    $productUnitId = $productUnit ? (int) $productUnit->id : $this->resolveProductUnitId((int) $item->product_id, $item->unit_id ? (int) $item->unit_id : null);

                    $this->stockMovementService->recordMovement(
                        productId: (int) $item->product_id,
                        warehouseId: (int) $invoice->warehouse_id,
                        productUnitId: $productUnitId,
                        movementType: 'sales',
                        quantity: (float) $item->quantity,
                        unitCost: (float) $item->cost_price,
                        reference: $invoice,
                        notes: "خصم مبيعات بموجب فاتورة رقم: {$invoice->invoice_number}",
                        userId: $userId
                    );
                }
            }

            // 3. توليد وترحيل القيد المحاسبي للفاتورة
            $this->salesAccountingService->createInvoiceJournalEntry($invoice);

            // 4. توليد وترحيل قيود الدفعات المقبوضة باستخدام الحساب المحاسبي الفعلي للصندوق أو البنك
            foreach ($invoice->payments as $payment) {
                $paymentAccountId = 0;

                if ($payment->box_id) {
                    $box = $payment->box ?? Box::find($payment->box_id);
                    $paymentAccountId = (int) ($box?->account_id ?? 0);
                    if ($paymentAccountId <= 0) {
                        throw new RuntimeException('الصندوق النقدي المحدد غير مربوط بحساب محاسبي في دليل الحسابات.');
                    }
                } elseif ($payment->bank_account_id) {
                    $bankAccount = $payment->bankAccount ?? BankAccount::find($payment->bank_account_id);
                    $paymentAccountId = (int) ($bankAccount?->account_id ?? 0);
                    if ($paymentAccountId <= 0) {
                        throw new RuntimeException('الحساب البنكي المحدد غير مربوط بحساب محاسبي في دليل الحسابات.');
                    }
                }

                if ($paymentAccountId > 0) {
                    $this->salesAccountingService->createInvoicePaymentJournalEntry(
                        invoice: $invoice,
                        amount: (float) $payment->amount,
                        paymentAccountId: $paymentAccountId,
                        referenceNumber: $payment->transaction_reference,
                        notes: $payment->notes
                    );
                }
            }

            // 5. تحديث حالة الترحيل
            $invoice->update([
                'posting_status' => 'posted',
                'posted_at' => Carbon::now(),
                'updated_by' => $userId,
            ]);

            return $this->find($invoice->id);
        });
    }

    public function cancel(SalesInvoice $invoice, int $userId): SalesInvoice
    {
        if ($invoice->status === 'cancelled') {
            throw new RuntimeException('الفاتورة ملغاة بالفعل.');
        }

        if ($invoice->returns()->exists()) {
            throw new RuntimeException('لا يمكن إلغاء فاتورة مرتبطة بمرتجعات قائمة.');
        }

        return DB::transaction(function () use ($invoice, $userId): SalesInvoice {
            if ($invoice->posting_status === 'posted') {
                $this->stockMovementService->clearDocumentMovements($invoice);
                $this->salesAccountingService->deleteInvoiceJournalEntries($invoice);
            }

            $invoice->update([
                'status' => 'cancelled',
                'posting_status' => 'unposted',
                'posted_at' => null,
                'updated_by' => $userId,
            ]);

            return $this->find($invoice->id);
        });
    }

    protected function syncInvoicePayments(SalesInvoice $invoice, array $paymentsData, int $userId): void
    {
        $user = User::find($userId);
        $existingPayments = $invoice->payments()->get()->keyBy('id');

        $submittedIds = collect($paymentsData)
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->toArray();

        foreach ($existingPayments as $existingId => $existingPayment) {
            if (! in_array($existingId, $submittedIds, true)) {
                $existingPayment->delete();
            }
        }

        foreach ($paymentsData as $pData) {
            $amount = round((float) ($pData['amount'] ?? 0), 2);
            if ($amount <= 0) {
                continue;
            }

            $method = $pData['payment_method'];
            $boxId = ! empty($pData['box_id'])
                ? (int) $pData['box_id']
                : ($method === 'cash' && $user?->default_box_id ? (int) $user->default_box_id : null);

            $bankAccountId = ! empty($pData['bank_account_id'])
                ? (int) $pData['bank_account_id']
                : (in_array($method, ['card', 'bank_transfer'], true) && $user?->default_bank_account_id
                    ? (int) $user->default_bank_account_id
                    : null);

            if (! empty($pData['id']) && $existingPayments->has((int) $pData['id'])) {
                $existingPayments->get((int) $pData['id'])->update([
                    'payment_method' => $method,
                    'amount' => $amount,
                    'box_id' => $boxId,
                    'bank_account_id' => $bankAccountId,
                    'transaction_reference' => $pData['transaction_reference'] ?? null,
                    'notes' => $pData['notes'] ?? null,
                ]);
            } else {
                $paymentNumber = $this->sequenceService->generateNumber(
                    'acc_receipt',
                    $invoice->branch_id ? (int) $invoice->branch_id : null,
                    null,
                    Carbon::parse($invoice->invoice_date)->toDateString()
                );

                $invoice->payments()->create([
                    'payment_number' => $paymentNumber,
                    'shift_id' => $invoice->shift_id,
                    'payment_method' => $method,
                    'amount' => $amount,
                    'currency_id' => $invoice->currency_id,
                    'exchange_rate' => $invoice->exchange_rate,
                    'box_id' => $boxId,
                    'bank_account_id' => $bankAccountId,
                    'transaction_reference' => $pData['transaction_reference'] ?? null,
                    'payment_date' => Carbon::now(),
                    'notes' => $pData['notes'] ?? null,
                    'created_by' => $userId,
                ]);
            }
        }
    }

    protected function validateStockAvailability(SalesInvoice $invoice, int $userId): void
    {
        $user = User::find($userId);
        if (! $user || ! $user->stock_control) {
            return;
        }

        $requiredQuantities = [];

        foreach ($invoice->items as $item) {
            $product = $item->product ?? Product::find($item->product_id);
            if (! $product || $product->type === 'service') {
                continue;
            }

            $productUnit = $this->resolveProductUnit((int) $item->product_id, $item->unit_id ? (int) $item->unit_id : null);
            $conversionFactor = $productUnit && (float) $productUnit->conversion_factor > 0
                ? (float) $productUnit->conversion_factor
                : 1.0;
            $soldBaseQty = (float) $item->quantity * $conversionFactor;

            $bom = $this->getActiveProductBom((int) $item->product_id);
            if ($bom && $bom->items->isNotEmpty()) {
                $bomItemsBreakdown = $this->decomposeBomItems($bom, $soldBaseQty);

                foreach ($bomItemsBreakdown as $component) {
                    $compProductId = $component['product_id'];
                    if (! isset($requiredQuantities[$compProductId])) {
                        $requiredQuantities[$compProductId] = [
                            'name' => $component['product_name'],
                            'qty' => 0.0,
                        ];
                    }
                    $requiredQuantities[$compProductId]['qty'] += $component['base_quantity'];
                }
            } else {
                $productId = (int) $item->product_id;
                if (! isset($requiredQuantities[$productId])) {
                    $requiredQuantities[$productId] = [
                        'name' => $product->name ?? "صنف رقم {$productId}",
                        'qty' => 0.0,
                    ];
                }
                $requiredQuantities[$productId]['qty'] += $soldBaseQty;
            }
        }

        foreach ($requiredQuantities as $productId => $demand) {
            $currentStock = (float) ProductStock::where('warehouse_id', $invoice->warehouse_id)
                ->where('product_id', $productId)
                ->sum('quantity');

            if (round($currentStock - $demand['qty'], 4) < 0) {
                throw new RuntimeException(
                    "عفواً، لا يمكن إتمام ترحيل الفاتورة لعدم توفر رصيد كافٍ في المستودع للصنف: [ {$demand['name']} ]. " .
                    "الرصيد المتوفر حالياً: ({$currentStock})، والكمية المطلوبة: ({$demand['qty']})."
                );
            }
        }
    }

    protected function calculateInvoiceFinancials(array $data): array
    {
        $calculatedItems = [];
        $subtotal = 0.0;
        $itemsTaxAmount = 0.0;

        foreach ($data['items'] as $item) {
            $quantity = (float) $item['quantity'];
            $unitPrice = (float) $item['unit_price'];
            $lineGross = round($quantity * $unitPrice, 2);

            $discountType = $item['discount_type'] ?? 'fixed';
            $discountValue = isset($item['discount_value']) ? (float) $item['discount_value'] : 0.0;
            $discountAmount = 0.0;

            if ($discountType === 'percentage') {
                $discountAmount = round($lineGross * ($discountValue / 100), 2);
            } else {
                $discountAmount = min($lineGross, round($discountValue, 2));
            }

            $lineSubtotal = round($lineGross - $discountAmount, 2);

            $taxRate = isset($item['tax_rate']) ? (float) $item['tax_rate'] : 0.0;
            $taxAmount = round($lineSubtotal * ($taxRate / 100), 2);
            $lineTotal = round($lineSubtotal + $taxAmount, 2);

            $productId = (int) $item['product_id'];
            $unitId = isset($item['unit_id']) && $item['unit_id'] !== null ? (int) $item['unit_id'] : null;

            $costPrice = isset($item['cost_price']) && (float) $item['cost_price'] > 0
                ? (float) $item['cost_price']
                : $this->calculateUnitCostPrice($productId, $unitId);

            $subtotal += $lineSubtotal;
            $itemsTaxAmount += $taxAmount;

            $itemRecord = $item;
            $itemRecord['quantity'] = $quantity;
            $itemRecord['unit_price'] = $unitPrice;
            $itemRecord['cost_price'] = $costPrice;
            $itemRecord['discount_type'] = $discountType;
            $itemRecord['discount_value'] = $discountValue;
            $itemRecord['discount_amount'] = $discountAmount;
            $itemRecord['tax_rate'] = $taxRate;
            $itemRecord['tax_amount'] = $taxAmount;
            $itemRecord['subtotal'] = $lineSubtotal;
            $itemRecord['total_amount'] = $lineTotal;

            $calculatedItems[] = $itemRecord;
        }

        $headerDiscountType = $data['discount_type'] ?? 'fixed';
        $headerDiscountValue = isset($data['discount_value']) ? (float) $data['discount_value'] : 0.0;
        $headerDiscountAmount = 0.0;

        if ($headerDiscountType === 'percentage') {
            $headerDiscountAmount = round($subtotal * ($headerDiscountValue / 100), 2);
        } else {
            $headerDiscountAmount = min($subtotal, round($headerDiscountValue, 2));
        }

        $taxableAmount = round($subtotal - $headerDiscountAmount, 2);
        $serviceCharge = isset($data['service_charge']) ? (float) $data['service_charge'] : 0.0;
        $deliveryFee = isset($data['delivery_fee']) ? (float) $data['delivery_fee'] : 0.0;

        $totalAmount = round($taxableAmount + $itemsTaxAmount + $serviceCharge + $deliveryFee, 2);

        return [
            'items' => $calculatedItems,
            'subtotal' => round($subtotal, 2),
            'discount_type' => $headerDiscountType,
            'discount_value' => $headerDiscountValue,
            'discount_amount' => $headerDiscountAmount,
            'taxable_amount' => $taxableAmount,
            'vat_amount' => round($itemsTaxAmount, 2),
            'service_charge' => round($serviceCharge, 2),
            'delivery_fee' => round($deliveryFee, 2),
            'total_amount' => max(0.0, $totalAmount),
        ];
    }

    protected function calculateUnitCostPrice(int $productId, ?int $unitId): float
    {
        $productUnit = $this->resolveProductUnit($productId, $unitId);
        $conversionFactor = $productUnit && (float) $productUnit->conversion_factor > 0
            ? (float) $productUnit->conversion_factor
            : 1.0;

        $bom = $this->getActiveProductBom($productId);
        if ($bom && $bom->items->isNotEmpty()) {
            $components = $this->decomposeBomItems($bom, $conversionFactor);
            $unitCost = 0.0;
            foreach ($components as $component) {
                $unitCost += ($component['quantity'] * $component['unit_cost']);
            }

            return round($unitCost, 4);
        }

        $baseCost = (float) Product::where('id', $productId)->value('cost_price');

        return round($baseCost * $conversionFactor, 4);
    }

    protected function getActiveProductBom(int $productId): ?Bom
    {
        return Bom::with(['items.rawMaterial', 'items.productUnit', 'productUnit'])
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->first();
    }

    protected function decomposeBomItems(Bom $bom, float $soldBaseQuantity): array
    {
        $bomOutputQty = (float) $bom->quantity > 0 ? (float) $bom->quantity : 1.0;
        $bomOutputUnitFactor = $bom->productUnit && (float) $bom->productUnit->conversion_factor > 0
            ? (float) $bom->productUnit->conversion_factor
            : 1.0;
        $bomBaseOutput = $bomOutputQty * $bomOutputUnitFactor;
        $multiplier = $soldBaseQuantity / $bomBaseOutput;

        $breakdown = [];

        foreach ($bom->items as $bomItem) {
            $rawMaterial = $bomItem->rawMaterial;
            if (! $rawMaterial) {
                continue;
            }

            $wasteFactor = 1.0 + ((float) $bomItem->waste_percentage / 100);
            $componentQty = (float) $bomItem->quantity * $wasteFactor * $multiplier;

            $rawProductUnit = $bomItem->productUnit
                ?? $this->resolveProductUnit((int) $rawMaterial->id, null);

            $rawUnitId = $rawProductUnit ? (int) $rawProductUnit->id : $this->resolveProductUnitId((int) $rawMaterial->id, null);
            $rawUnitFactor = $rawProductUnit && (float) $rawProductUnit->conversion_factor > 0
                ? (float) $rawProductUnit->conversion_factor
                : 1.0;

            $rawBaseCost = (float) $rawMaterial->cost_price;
            $rawUnitCost = round($rawBaseCost * $rawUnitFactor, 4);

            $breakdown[] = [
                'product_id' => (int) $rawMaterial->id,
                'product_name' => $rawMaterial->name,
                'product_unit_id' => $rawUnitId,
                'quantity' => round($componentQty, 6),
                'base_quantity' => round($componentQty * $rawUnitFactor, 6),
                'unit_cost' => $rawUnitCost,
            ];
        }

        return $breakdown;
    }

    protected function resolveProductUnit(int $productId, ?int $unitId): ?ProductUnit
    {
        if ($unitId) {
            $productUnit = ProductUnit::where('product_id', $productId)
                ->where(function (Builder $builder) use ($unitId): void {
                    $builder->where('id', $unitId)
                        ->orWhere('unit_id', $unitId);
                })
                ->first();

            if ($productUnit) {
                return $productUnit;
            }
        }

        return ProductUnit::where('product_id', $productId)
            ->orderByDesc('is_base_unit')
            ->first();
    }

    protected function resolveProductUnitId(int $productId, ?int $unitId): int
    {
        $productUnit = $this->resolveProductUnit($productId, $unitId);
        if ($productUnit) {
            return (int) $productUnit->id;
        }

        return $unitId ?? 1;
    }
}