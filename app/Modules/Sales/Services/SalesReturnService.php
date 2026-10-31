<?php

declare(strict_types=1);

namespace App\Modules\Sales\Services;

use App\Modules\Core\Services\SequenceService;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\ProductUnit;
use App\Modules\Inventory\Services\StockMovementService;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesInvoiceItem;
use App\Modules\Sales\Models\SalesReturn;
use App\Modules\Sales\Models\SalesReturnItem;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SalesReturnService
{
    public function __construct(
        protected SequenceService $sequenceService,
        protected StockMovementService $stockMovementService,
        protected SalesAccountingService $salesAccountingService
    ) {}

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = SalesReturn::query()
            ->with([
                'partner',
                'warehouse',
                'costCenter',
                'currency',
                'shift',
                'invoice',
                'creator',
            ])
            ->withCount(['items', 'refundPayments']);

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('return_number', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('partner', function (Builder $partnerQuery) use ($search): void {
                        $partnerQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    })
                    ->orWhereHas('invoice', function (Builder $invoiceQuery) use ($search): void {
                        $invoiceQuery->where('invoice_number', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['posting_status'])) {
            $query->where('posting_status', $filters['posting_status']);
        }

        if (! empty($filters['refund_status'])) {
            $query->where('refund_status', $filters['refund_status']);
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

        if (! empty($filters['sales_invoice_id'])) {
            $query->where('sales_invoice_id', $filters['sales_invoice_id']);
        }

        if (! empty($filters['shift_id'])) {
            $query->where('shift_id', $filters['shift_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('return_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('return_date', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    public function find(int $id): SalesReturn
    {
        return SalesReturn::with([
            'partner',
            'warehouse',
            'costCenter',
            'currency',
            'shift',
            'invoice',
            'creator',
            'updater',
            'items.product',
            'items.unit',
            'items.invoiceItem',
            'refundPayments.box',
            'refundPayments.bankAccount',
            'refundPayments.creator',
        ])->findOrFail($id);
    }

    public function create(array $data, int $userId): SalesReturn
    {
        return DB::transaction(function () use ($data, $userId): SalesReturn {
            $returnDate = ! empty($data['return_date'])
                ? Carbon::parse($data['return_date'])
                : Carbon::now();

            $branchId = isset($data['branch_id']) ? (int) $data['branch_id'] : null;

            $returnNumber = $this->sequenceService->generateNumber(
                'sales_return',
                $branchId,
                null,
                $returnDate->toDateString()
            );

            if (! empty($data['sales_invoice_id'])) {
                $this->validateReturnedQuantitiesAgainstInvoice((int) $data['sales_invoice_id'], $data['items']);
            }

            $calculatedData = $this->calculateReturnFinancials($data);

            $salesReturn = SalesReturn::create([
                'return_number' => $returnNumber,
                'sales_invoice_id' => $data['sales_invoice_id'] ?? null,
                'shift_id' => $data['shift_id'] ?? null,
                'return_date' => $returnDate,
                'partner_id' => $data['partner_id'],
                'branch_id' => $branchId,
                'warehouse_id' => $data['warehouse_id'],
                'cost_center_id' => $data['cost_center_id'] ?? null,
                'currency_id' => $data['currency_id'] ?? null,
                'exchange_rate' => $data['exchange_rate'] ?? 1.0000,
                'status' => 'draft',
                'posting_status' => 'unposted',
                'posted_at' => null,
                'refund_status' => 'unpaid',
                'subtotal' => $calculatedData['subtotal'],
                'discount_amount' => $calculatedData['discount_amount'],
                'taxable_amount' => $calculatedData['taxable_amount'],
                'vat_amount' => $calculatedData['vat_amount'],
                'total_amount' => $calculatedData['total_amount'],
                'refunded_amount' => 0.00,
                'remaining_amount' => $calculatedData['total_amount'],
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            foreach ($calculatedData['items'] as $itemData) {
                $salesReturn->items()->create([
                    'sales_invoice_item_id' => $itemData['sales_invoice_item_id'] ?? null,
                    'product_id' => $itemData['product_id'],
                    'unit_id' => $itemData['unit_id'] ?? null,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'cost_price' => $itemData['cost_price'],
                    'subtotal' => $itemData['subtotal'],
                    'discount_amount' => $itemData['discount_amount'],
                    'tax_rate' => $itemData['tax_rate'],
                    'tax_amount' => $itemData['tax_amount'],
                    'total_amount' => $itemData['total_amount'],
                    'item_condition' => $itemData['item_condition'] ?? 'good',
                    'notes' => $itemData['notes'] ?? null,
                ]);
            }

            return $this->find($salesReturn->id);
        });
    }

    public function update(SalesReturn $salesReturn, array $data, int $userId): SalesReturn
    {
        if ($salesReturn->status !== 'draft' || $salesReturn->posting_status === 'posted') {
            throw new RuntimeException('لا يمكن تعديل المرتجع إلا إذا كان في حالة مسودة وغير مرحل.');
        }

        return DB::transaction(function () use ($salesReturn, $data, $userId): SalesReturn {
            $salesInvoiceId = array_key_exists('sales_invoice_id', $data)
                ? ($data['sales_invoice_id'] ? (int) $data['sales_invoice_id'] : null)
                : $salesReturn->sales_invoice_id;

            if ($salesInvoiceId && ! empty($data['items'])) {
                $this->validateReturnedQuantitiesAgainstInvoice(
                    $salesInvoiceId,
                    $data['items'],
                    (int) $salesReturn->id
                );
            }

            $calculatedData = $this->calculateReturnFinancials($data, $salesReturn);

            $refundedAmount = (float) $salesReturn->refundPayments()->sum('amount');
            $totalAmount = $calculatedData['total_amount'];
            $remainingAmount = max(0.0, round($totalAmount - $refundedAmount, 2));

            $refundStatus = 'unpaid';
            if ($refundedAmount >= $totalAmount && $totalAmount > 0) {
                $refundStatus = 'refunded';
            } elseif ($refundedAmount > 0) {
                $refundStatus = 'partially_refunded';
            }

            $salesReturn->update([
                'sales_invoice_id' => $salesInvoiceId,
                'shift_id' => array_key_exists('shift_id', $data) ? $data['shift_id'] : $salesReturn->shift_id,
                'return_date' => ! empty($data['return_date']) ? Carbon::parse($data['return_date']) : $salesReturn->return_date,
                'partner_id' => $data['partner_id'] ?? $salesReturn->partner_id,
                'branch_id' => array_key_exists('branch_id', $data) ? $data['branch_id'] : $salesReturn->branch_id,
                'warehouse_id' => $data['warehouse_id'] ?? $salesReturn->warehouse_id,
                'cost_center_id' => array_key_exists('cost_center_id', $data) ? $data['cost_center_id'] : $salesReturn->cost_center_id,
                'currency_id' => array_key_exists('currency_id', $data) ? $data['currency_id'] : $salesReturn->currency_id,
                'exchange_rate' => $data['exchange_rate'] ?? $salesReturn->exchange_rate,
                'subtotal' => $calculatedData['subtotal'],
                'discount_amount' => $calculatedData['discount_amount'],
                'taxable_amount' => $calculatedData['taxable_amount'],
                'vat_amount' => $calculatedData['vat_amount'],
                'total_amount' => $totalAmount,
                'refunded_amount' => $refundedAmount,
                'remaining_amount' => $remainingAmount,
                'refund_status' => $refundStatus,
                'reason' => array_key_exists('reason', $data) ? $data['reason'] : $salesReturn->reason,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $salesReturn->notes,
                'updated_by' => $userId,
            ]);

            $existingItems = $salesReturn->items()->get()->keyBy('id');
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
                    /** @var SalesReturnItem $existingItem */
                    $existingItem = $existingItems->get($itemData['id']);
                    $existingItem->update([
                        'sales_invoice_item_id' => $itemData['sales_invoice_item_id'] ?? $existingItem->sales_invoice_item_id,
                        'product_id' => $itemData['product_id'],
                        'unit_id' => $itemData['unit_id'] ?? null,
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                        'cost_price' => $itemData['cost_price'],
                        'subtotal' => $itemData['subtotal'],
                        'discount_amount' => $itemData['discount_amount'],
                        'tax_rate' => $itemData['tax_rate'],
                        'tax_amount' => $itemData['tax_amount'],
                        'total_amount' => $itemData['total_amount'],
                        'item_condition' => $itemData['item_condition'] ?? 'good',
                        'notes' => $itemData['notes'] ?? null,
                    ]);
                } else {
                    $salesReturn->items()->create([
                        'sales_invoice_item_id' => $itemData['sales_invoice_item_id'] ?? null,
                        'product_id' => $itemData['product_id'],
                        'unit_id' => $itemData['unit_id'] ?? null,
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                        'cost_price' => $itemData['cost_price'],
                        'subtotal' => $itemData['subtotal'],
                        'discount_amount' => $itemData['discount_amount'],
                        'tax_rate' => $itemData['tax_rate'],
                        'tax_amount' => $itemData['tax_amount'],
                        'total_amount' => $itemData['total_amount'],
                        'item_condition' => $itemData['item_condition'] ?? 'good',
                        'notes' => $itemData['notes'] ?? null,
                    ]);
                }
            }

            return $this->find($salesReturn->id);
        });
    }

    public function delete(SalesReturn $salesReturn): bool
    {
        if ($salesReturn->status !== 'draft' || $salesReturn->posting_status === 'posted') {
            throw new RuntimeException('لا يمكن حذف المرتجع إلا إذا كان في حالة مسودة وغير مرحل.');
        }

        return DB::transaction(function () use ($salesReturn): bool {
            $salesReturn->refundPayments()->delete();
            $salesReturn->items()->delete();

            return (bool) $salesReturn->delete();
        });
    }

    public function confirm(SalesReturn $salesReturn, int $userId): SalesReturn
    {
        if ($salesReturn->status !== 'draft') {
            throw new RuntimeException('لا يمكن تأكيد المرتجع إلا إذا كان في حالة مسودة.');
        }

        if ($salesReturn->items()->count() === 0) {
            throw new RuntimeException('لا يمكن تأكيد مرتجع بدون بنود.');
        }

        $salesReturn->update([
            'status' => 'confirmed',
            'updated_by' => $userId,
        ]);

        return $this->find($salesReturn->id);
    }

    public function post(SalesReturn $salesReturn, int $userId): SalesReturn
    {
        if ($salesReturn->status !== 'confirmed') {
            throw new RuntimeException('يجب تأكيد المرتجع أولاً قبل الترحيل.');
        }

        if ($salesReturn->posting_status === 'posted') {
            throw new RuntimeException('المرتجع مرحل بالفعل مسبقاً.');
        }

        return DB::transaction(function () use ($salesReturn, $userId): SalesReturn {
            $salesReturn->loadMissing(['items', 'refundPayments']);

            // 1. إعادة إدخال البضاعة السليمة (good condition) إلى المستودع
            foreach ($salesReturn->items as $item) {
                if ($item->item_condition === 'good') {
                    $productUnitId = $this->resolveProductUnitId((int) $item->product_id, $item->unit_id ? (int) $item->unit_id : null);

                    $this->stockMovementService->recordMovement(
                        productId: (int) $item->product_id,
                        warehouseId: (int) $salesReturn->warehouse_id,
                        productUnitId: $productUnitId,
                        movementType: 'in',
                        quantity: (float) $item->quantity,
                        unitCost: (float) $item->cost_price,
                        reference: $salesReturn,
                        notes: "إرجاع بضاعة سليمة بموجب إشعار دائن رقم: {$salesReturn->return_number}",
                        userId: $userId
                    );
                }
            }

            // 2. توليد وترحيل القيد المحاسبي للمردود
            $this->salesAccountingService->createReturnJournalEntry($salesReturn);

            // 3. ترحيل قيود سندات استرداد الأموال إن وُجدت مسجلة مسبقاً
            foreach ($salesReturn->refundPayments as $payment) {
                $paymentAccountId = $payment->box_id
                    ? (int) $payment->box_id
                    : (int) ($payment->bank_account_id ?? 0);

                if ($paymentAccountId > 0) {
                    $this->salesAccountingService->createReturnPaymentJournalEntry(
                        return: $salesReturn,
                        amount: (float) $payment->amount,
                        paymentAccountId: $paymentAccountId,
                        referenceNumber: $payment->transaction_reference,
                        notes: $payment->notes
                    );
                }
            }

            $salesReturn->update([
                'posting_status' => 'posted',
                'posted_at' => Carbon::now(),
                'updated_by' => $userId,
            ]);

            return $this->find($salesReturn->id);
        });
    }

    public function cancel(SalesReturn $salesReturn, int $userId): SalesReturn
    {
        if ($salesReturn->status === 'cancelled') {
            throw new RuntimeException('المرتجع ملغى بالفعل.');
        }

        if ($salesReturn->refundPayments()->exists()) {
            throw new RuntimeException('لا يمكن إلغاء مرتجع مسجل عليه سندات صرف مالي مستردة. يجب إلغاء السندات أولاً.');
        }

        return DB::transaction(function () use ($salesReturn, $userId): SalesReturn {
            // عكس وإلغاء حركات المخزون والقيود في حال كان المرتجع مرحلاً
            if ($salesReturn->posting_status === 'posted') {
                $this->stockMovementService->clearDocumentMovements($salesReturn);
                $this->salesAccountingService->deleteReturnJournalEntries($salesReturn);
            }

            $salesReturn->update([
                'status' => 'cancelled',
                'posting_status' => 'unposted',
                'posted_at' => null,
                'updated_by' => $userId,
            ]);

            return $this->find($salesReturn->id);
        });
    }

    protected function calculateReturnFinancials(array $data, ?SalesReturn $existingReturn = null): array
    {
        $itemsData = $data['items'] ?? ($existingReturn ? $existingReturn->items->toArray() : []);
        $calculatedItems = [];
        $subtotal = 0.0;
        $totalDiscount = 0.0;
        $totalVat = 0.0;

        foreach ($itemsData as $item) {
            $quantity = (float) $item['quantity'];
            $unitPrice = (float) $item['unit_price'];
            $lineGross = round($quantity * $unitPrice, 2);

            $discountAmount = isset($item['discount_amount']) ? min($lineGross, round((float) $item['discount_amount'], 2)) : 0.0;
            $lineSubtotal = round($lineGross - $discountAmount, 2);

            $taxRate = isset($item['tax_rate']) ? (float) $item['tax_rate'] : 0.0;
            $taxAmount = round($lineSubtotal * ($taxRate / 100), 2);
            $lineTotal = round($lineSubtotal + $taxAmount, 2);

            $costPrice = isset($item['cost_price']) && (float) $item['cost_price'] > 0
                ? (float) $item['cost_price']
                : (float) Product::where('id', $item['product_id'])->value('cost_price');

            $subtotal += $lineGross;
            $totalDiscount += $discountAmount;
            $totalVat += $taxAmount;

            $itemRecord = $item;
            $itemRecord['quantity'] = $quantity;
            $itemRecord['unit_price'] = $unitPrice;
            $itemRecord['cost_price'] = $costPrice;
            $itemRecord['subtotal'] = $lineSubtotal;
            $itemRecord['discount_amount'] = $discountAmount;
            $itemRecord['tax_rate'] = $taxRate;
            $itemRecord['tax_amount'] = $taxAmount;
            $itemRecord['total_amount'] = $lineTotal;

            $calculatedItems[] = $itemRecord;
        }

        $taxableAmount = round($subtotal - $totalDiscount, 2);
        $totalAmount = round($taxableAmount + $totalVat, 2);

        return [
            'items' => $calculatedItems,
            'subtotal' => round($subtotal, 2),
            'discount_amount' => round($totalDiscount, 2),
            'taxable_amount' => $taxableAmount,
            'vat_amount' => round($totalVat, 2),
            'total_amount' => max(0.0, $totalAmount),
        ];
    }

    protected function validateReturnedQuantitiesAgainstInvoice(int $invoiceId, array $items, ?int $ignoreReturnId = null): void
    {
        $invoice = SalesInvoice::with('items')->findOrFail($invoiceId);
        $invoiceItems = $invoice->items->keyBy('id');

        $returnedTotals = SalesReturnItem::whereHas('salesReturn', function (Builder $query) use ($invoiceId, $ignoreReturnId): void {
            $query->where('sales_invoice_id', $invoiceId)
                ->where('status', '!=', 'cancelled');
            if ($ignoreReturnId) {
                $query->where('id', '!=', $ignoreReturnId);
            }
        })
            ->selectRaw('sales_invoice_item_id, SUM(quantity) as total_returned')
            ->groupBy('sales_invoice_item_id')
            ->pluck('total_returned', 'sales_invoice_item_id')
            ->toArray();

        foreach ($items as $item) {
            $invoiceItemId = $item['sales_invoice_item_id'] ?? null;
            if (! $invoiceItemId) {
                continue;
            }

            if (! $invoiceItems->has($invoiceItemId)) {
                throw new RuntimeException("البند رقم ({$invoiceItemId}) لا ينتمي للفاتورة الأصلية رقم ({$invoice->invoice_number}).");
            }

            /** @var SalesInvoiceItem $invItem */
            $invItem = $invoiceItems->get($invoiceItemId);
            $alreadyReturned = (float) ($returnedTotals[$invoiceItemId] ?? 0.0);
            $availableToReturn = round((float) $invItem->quantity - $alreadyReturned, 4);
            $currentReturnQty = (float) $item['quantity'];

            if ($currentReturnQty > $availableToReturn) {
                throw new RuntimeException(
                    "الكمية المراد إرجاعها ({$currentReturnQty}) تتجاوز الكمية المتاحة للإرجاع ({$availableToReturn}) للبند رقم: {$invoiceItemId}."
                );
            }
        }
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