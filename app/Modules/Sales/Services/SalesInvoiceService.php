<?php

declare(strict_types=1);

namespace App\Modules\Sales\Services;

use App\Modules\Core\Services\SequenceService;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\ProductUnit;
use App\Modules\Inventory\Services\StockMovementService;
use App\Modules\Sales\Models\PosShift;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesInvoiceItem;
use App\Modules\Sales\Models\SalesInvoicePayment;
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
                'partner',
                'warehouse',
                'costCenter',
                'currency',
                'shift',
                'creator',
            ])
            ->withCount(['items', 'payments', 'returns']);

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('reference_number', 'like', "%{$search}%")
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
            'partner',
            'warehouse',
            'costCenter',
            'currency',
            'shift',
            'creator',
            'updater',
            'items.product',
            'items.unit',
            'payments.box',
            'payments.bankAccount',
            'payments.creator',
            'returns',
        ])->findOrFail($id);
    }

    public function create(array $data, int $userId): SalesInvoice
    {
        return DB::transaction(function () use ($data, $userId): SalesInvoice {
            $invoiceDate = ! empty($data['invoice_date'])
                ? Carbon::parse($data['invoice_date'])
                : Carbon::now();

            $branchId = isset($data['branch_id']) ? (int) $data['branch_id'] : null;

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
                'branch_id' => $branchId,
                'warehouse_id' => $data['warehouse_id'],
                'cost_center_id' => $data['cost_center_id'] ?? null,
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

            return $this->find($invoice->id);
        });
    }

    public function update(SalesInvoice $invoice, array $data, int $userId): SalesInvoice
    {
        if ($invoice->status !== 'draft' || $invoice->posting_status === 'posted') {
            throw new RuntimeException('لا يمكن تعديل الفاتورة إلا إذا كانت في حالة مسودة وغير مرحلة.');
        }

        return DB::transaction(function () use ($invoice, $data, $userId): SalesInvoice {
            $calculatedData = $this->calculateInvoiceFinancials($data);

            $existingPaymentsTotal = (float) $invoice->payments()->sum('amount');
            $totalAmount = $calculatedData['total_amount'];
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

            return $this->find($invoice->id);
        });
    }

    public function delete(SalesInvoice $invoice): bool
    {
        if ($invoice->status !== 'draft' || $invoice->posting_status === 'posted') {
            throw new RuntimeException('لا يمكن حذف الفاتورة إلا إذا كانت في حالة مسودة وغير مرحلة.');
        }

        return DB::transaction(function () use ($invoice): bool {
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
            $invoice->loadMissing(['items', 'payments']);

            // 1. توليد حركات خصم المخزون اللحظية لكل بند
            foreach ($invoice->items as $item) {
                $productUnitId = $this->resolveProductUnitId((int) $item->product_id, $item->unit_id ? (int) $item->unit_id : null);

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

            // 2. توليد وترحيل القيد المحاسبي للفاتورة
            $this->salesAccountingService->createInvoiceJournalEntry($invoice);

            // 3. توليد وترحيل قيود الدفعات المقبوضة إن وجدت
            foreach ($invoice->payments as $payment) {
                $paymentAccountId = $payment->box_id
                    ? (int) $payment->box_id
                    : (int) ($payment->bank_account_id ?? 0);

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

            // 4. تحديث حالة الترحيل
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
            // 1. عكس وإلغاء حركات المخزون في حال تم ترحيلها
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

            $costPrice = isset($item['cost_price']) && (float) $item['cost_price'] > 0
                ? (float) $item['cost_price']
                : (float) Product::where('id', $item['product_id'])->value('cost_price');

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

    protected function resolveProductUnitId(int $productId, ?int $unitId): int
    {
        if ($unitId) {
            $productUnit = ProductUnit::where('product_id', $productId)
                ->where(function (Builder $builder) use ($unitId): void {
                    $builder->where('id', $unitId)
                        ->orWhere('unit_id', $unitId);
                })
                ->first();

            if ($productUnit) {
                return (int) $productUnit->id;
            }
        }

        $defaultUnit = ProductUnit::where('product_id', $productId)->first();
        if ($defaultUnit) {
            return (int) $defaultUnit->id;
        }

        return $unitId ?? 1;
    }
}