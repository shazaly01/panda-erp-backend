<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Services;

use App\Modules\Inventory\Services\StockMovementService;
use App\Modules\Purchasing\Enums\BillStatus;
use App\Modules\Purchasing\Enums\PurchaseOrderStatus;
use App\Modules\Purchasing\Models\PurchaseBill;
use App\Modules\Purchasing\Models\PurchaseBillItem;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseOrderItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PurchaseBillService
{
    public function __construct(
        protected readonly PurchasingAccountingService $accountingService,
        protected readonly StockMovementService $stockMovementService
    ) {}

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = PurchaseBill::query()
            ->with([
                'supplier.payableAccount',
                'warehouse',
                'currency',
                'purchaseOrder',
                'receipt',
                'poster',
            ])
            ->withCount('items');

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('bill_number', 'like', "%{$search}%")
                    ->orWhere('supplier_bill_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function (Builder $supplierQuery) use ($search): void {
                        $supplierQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['supplier_id'])) {
            $supplierFilter = $filters['supplier_id'];
            if (is_numeric($supplierFilter)) {
                $query->where('supplier_id', (int) $supplierFilter);
            } else {
                $query->whereHas('supplier', function (Builder $supplierQuery) use ($supplierFilter): void {
                    $supplierQuery->where('name', 'like', "%{$supplierFilter}%");
                });
            }
        }

        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (! empty($filters['currency_id'])) {
            $query->where('currency_id', $filters['currency_id']);
        }

        if (! empty($filters['purchase_order_id'])) {
            $query->where('purchase_order_id', $filters['purchase_order_id']);
        }

        if (! empty($filters['receipt_id'])) {
            $query->where('receipt_id', $filters['receipt_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('bill_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('bill_date', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    public function find(int $id): PurchaseBill
    {
        return PurchaseBill::with([
            'supplier.payableAccount',
            'warehouse',
            'currency',
            'purchaseOrder',
            'receipt',
            'poster',
            'creator',
            'updater',
            'stockMovements',
            'journalEntries.details.account',
            'voucherDetails.voucher.box',
            'voucherDetails.voucher.bankAccount',
            'voucherDetails.voucher.currency',
            'items.product',
            'items.productUnit',
            'items.account',
            'items.orderItem',
            'items.receiptItem',
            'items.creator',
            'items.updater',
        ])->findOrFail($id);
    }

    public function create(array $data, int $userId): PurchaseBill
    {
        return DB::transaction(function () use ($data, $userId): PurchaseBill {
            $billNumber = $this->generateBillNumber();
            $calculatedData = $this->calculateBillFinancials($data);

            $bill = PurchaseBill::create([
                'bill_number' => $billNumber,
                'supplier_bill_number' => $data['supplier_bill_number'] ?? null,
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'receipt_id' => $data['receipt_id'] ?? null,
                'currency_id' => $data['currency_id'],
                'exchange_rate' => $data['exchange_rate'] ?? 1.0000,
                'bill_date' => $data['bill_date'] ?? Carbon::now()->toDateString(),
                'due_date' => $data['due_date'] ?? Carbon::now()->toDateString(),
                'status' => BillStatus::DRAFT,
                'subtotal' => $calculatedData['subtotal'],
                'discount_type' => $calculatedData['discount_type'],
                'discount_value' => $calculatedData['discount_value'],
                'discount_amount' => $calculatedData['discount_amount'],
                'tax_amount' => $calculatedData['tax_amount'],
                'shipping_cost' => $calculatedData['shipping_cost'],
                'total_amount' => $calculatedData['total_amount'],
                'paid_amount' => 0.0000,
                'remaining_amount' => $calculatedData['total_amount'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            foreach ($calculatedData['items'] as $itemData) {
                $bill->items()->create([
                    'purchase_order_item_id' => $itemData['purchase_order_item_id'] ?? null,
                    'receipt_item_id' => $itemData['receipt_item_id'] ?? null,
                    'product_id' => $itemData['product_id'],
                    'product_unit_id' => $itemData['product_unit_id'],
                    'account_id' => $itemData['account_id'] ?? null,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'discount_percentage' => $itemData['discount_percentage'],
                    'discount_amount' => $itemData['discount_amount'],
                    'tax_rate' => $itemData['tax_rate'],
                    'tax_amount' => $itemData['tax_amount'],
                    'subtotal' => $itemData['subtotal'],
                    'total' => $itemData['total'],
                    'notes' => $itemData['notes'] ?? null,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }

            return $this->find($bill->id);
        });
    }

    public function createAndPost(array $data, int $userId): PurchaseBill
    {
        return DB::transaction(function () use ($data, $userId): PurchaseBill {
            $bill = $this->create($data, $userId);

            return $this->post($bill, $userId);
        });
    }

    public function update(PurchaseBill $bill, array $data, int $userId): PurchaseBill
    {
        if ($bill->status !== BillStatus::DRAFT) {
            throw new RuntimeException('لا يمكن تعديل فاتورة الشراء إلا إذا كانت في حالة مسودة.');
        }

        return DB::transaction(function () use ($bill, $data, $userId): PurchaseBill {
            $calculatedData = $this->calculateBillFinancials($data);

            $bill->update([
                'supplier_bill_number' => array_key_exists('supplier_bill_number', $data) ? $data['supplier_bill_number'] : $bill->supplier_bill_number,
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => array_key_exists('warehouse_id', $data) ? $data['warehouse_id'] : $bill->warehouse_id,
                'purchase_order_id' => array_key_exists('purchase_order_id', $data) ? $data['purchase_order_id'] : $bill->purchase_order_id,
                'receipt_id' => array_key_exists('receipt_id', $data) ? $data['receipt_id'] : $bill->receipt_id,
                'currency_id' => $data['currency_id'],
                'exchange_rate' => $data['exchange_rate'] ?? $bill->exchange_rate,
                'bill_date' => $data['bill_date'] ?? $bill->bill_date,
                'due_date' => $data['due_date'] ?? $bill->due_date,
                'subtotal' => $calculatedData['subtotal'],
                'discount_type' => $calculatedData['discount_type'],
                'discount_value' => $calculatedData['discount_value'],
                'discount_amount' => $calculatedData['discount_amount'],
                'tax_amount' => $calculatedData['tax_amount'],
                'shipping_cost' => $calculatedData['shipping_cost'],
                'total_amount' => $calculatedData['total_amount'],
                'remaining_amount' => max(0.0, round($calculatedData['total_amount'] - (float) $bill->paid_amount, 4)),
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $bill->notes,
                'updated_by' => $userId,
            ]);

            $existingItems = $bill->items()->get()->keyBy('id');
            $submittedItemIds = collect($calculatedData['items'])->pluck('id')->filter()->map(fn ($id) => (int) $id)->toArray();

            foreach ($existingItems as $existingId => $existingItem) {
                if (! in_array($existingId, $submittedItemIds, true)) {
                    $existingItem->delete();
                }
            }

            foreach ($calculatedData['items'] as $itemData) {
                if (! empty($itemData['id']) && $existingItems->has($itemData['id'])) {
                    /** @var PurchaseBillItem $existingItem */
                    $existingItem = $existingItems->get($itemData['id']);
                    $existingItem->update([
                        'purchase_order_item_id' => $itemData['purchase_order_item_id'] ?? $existingItem->purchase_order_item_id,
                        'receipt_item_id' => $itemData['receipt_item_id'] ?? $existingItem->receipt_item_id,
                        'product_id' => $itemData['product_id'],
                        'product_unit_id' => $itemData['product_unit_id'],
                        'account_id' => $itemData['account_id'] ?? $existingItem->account_id,
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                        'discount_percentage' => $itemData['discount_percentage'],
                        'discount_amount' => $itemData['discount_amount'],
                        'tax_rate' => $itemData['tax_rate'],
                        'tax_amount' => $itemData['tax_amount'],
                        'subtotal' => $itemData['subtotal'],
                        'total' => $itemData['total'],
                        'notes' => $itemData['notes'] ?? null,
                        'updated_by' => $userId,
                    ]);
                } else {
                    $bill->items()->create([
                        'purchase_order_item_id' => $itemData['purchase_order_item_id'] ?? null,
                        'receipt_item_id' => $itemData['receipt_item_id'] ?? null,
                        'product_id' => $itemData['product_id'],
                        'product_unit_id' => $itemData['product_unit_id'],
                        'account_id' => $itemData['account_id'] ?? null,
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                        'discount_percentage' => $itemData['discount_percentage'],
                        'discount_amount' => $itemData['discount_amount'],
                        'tax_rate' => $itemData['tax_rate'],
                        'tax_amount' => $itemData['tax_amount'],
                        'subtotal' => $itemData['subtotal'],
                        'total' => $itemData['total'],
                        'notes' => $itemData['notes'] ?? null,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);
                }
            }

            return $this->find($bill->id);
        });
    }

    public function delete(PurchaseBill $bill): bool
    {
        if ($bill->status !== BillStatus::DRAFT) {
            throw new RuntimeException('لا يمكن حذف فاتورة الشراء إلا إذا كانت في حالة مسودة.');
        }

        return DB::transaction(function () use ($bill): bool {
            $bill->items()->delete();
            return (bool) $bill->delete();
        });
    }

    public function post(PurchaseBill $bill, int $userId): PurchaseBill
    {
        if ($bill->status !== BillStatus::DRAFT) {
            throw new RuntimeException('لا يمكن ترحيل فاتورة الشراء إلا إذا كانت في حالة مسودة.');
        }

        if ($bill->items()->count() === 0) {
            throw new RuntimeException('لا يمكن ترحيل فاتورة شراء لا تحتوي على أي بنود.');
        }

        return DB::transaction(function () use ($bill, $userId): PurchaseBill {
            $bill->loadMissing(['items', 'purchaseOrder.items']);

            // 1. توليد وترحيل قيد استحقاق الفاتورة آلياً
            $this->accountingService->createBillJournalEntry($bill);

            // 2. فحص حالة الشراء المباشر: إذا وجد مستودع ولا يوجد سند استلام مسبق، يتم تحريك المخزن فوراً
            $isDirectInventoryPurchase = ! empty($bill->warehouse_id) && empty($bill->receipt_id);

            if ($isDirectInventoryPurchase) {
                foreach ($bill->items as $item) {
                    $quantity = (float) $item->quantity;
                    if ($quantity > 0) {
                        $unitCost = round((float) $item->subtotal / $quantity, 4);

                        $this->stockMovementService->recordMovement(
                            productId: (int) $item->product_id,
                            warehouseId: (int) $bill->warehouse_id,
                            productUnitId: (int) $item->product_unit_id,
                            movementType: 'purchase',
                            quantity: $quantity,
                            unitCost: $unitCost,
                            reference: $bill,
                            notes: "إثبات مشتريات مباشرة - فاتورة رقم: {$bill->bill_number}",
                            userId: $userId
                        );
                    }
                }
            }

            // 3. تحديث كميات الفوترة والاستلام في أمر الشراء إن وجد
            foreach ($bill->items as $item) {
                if ($item->purchase_order_item_id && (float) $item->quantity > 0) {
                    $this->incrementOrderBilledQuantity(
                        (int) $item->purchase_order_item_id,
                        (float) $item->quantity,
                        $userId
                    );

                    if ($isDirectInventoryPurchase) {
                        $this->incrementOrderReceivedQuantity(
                            (int) $item->purchase_order_item_id,
                            (float) $item->quantity,
                            $userId
                        );
                    }
                }
            }

            if ($bill->purchase_order_id) {
                $this->syncPurchaseOrderStatus((int) $bill->purchase_order_id, $userId);
            }

            // 4. تحديث حالة الفاتورة وبيانات الترحيل
            $bill->update([
                'status' => BillStatus::POSTED,
                'posted_by' => $userId,
                'posted_at' => Carbon::now(),
                'updated_by' => $userId,
            ]);

            return $this->find($bill->id);
        });
    }

    public function cancel(PurchaseBill $bill, int $userId): PurchaseBill
    {
        if ($bill->status === BillStatus::CANCELLED) {
            throw new RuntimeException('هذه الفاتورة ملغية بالفعل.');
        }

        if ((float) $bill->paid_amount > 0.0001) {
            throw new RuntimeException('لا يمكن إلغاء فاتورة شراء تم سداد جزء منها أو سدادها بالكامل.');
        }

        return DB::transaction(function () use ($bill, $userId): PurchaseBill {
            $bill->loadMissing('items');

            if ($bill->status === BillStatus::POSTED) {
                $this->stockMovementService->clearDocumentMovements($bill);
                $this->accountingService->deleteBillJournalEntries($bill);

                $wasDirectInventoryPurchase = ! empty($bill->warehouse_id) && empty($bill->receipt_id);

                foreach ($bill->items as $item) {
                    if ($item->purchase_order_item_id && (float) $item->quantity > 0) {
                        $this->decrementOrderBilledQuantity(
                            (int) $item->purchase_order_item_id,
                            (float) $item->quantity,
                            $userId
                        );

                        if ($wasDirectInventoryPurchase) {
                            $this->decrementOrderReceivedQuantity(
                                (int) $item->purchase_order_item_id,
                                (float) $item->quantity,
                                $userId
                            );
                        }
                    }
                }

                if ($bill->purchase_order_id) {
                    $this->syncPurchaseOrderStatus((int) $bill->purchase_order_id, $userId);
                }
            }

            $bill->update([
                'status' => BillStatus::CANCELLED,
                'updated_by' => $userId,
            ]);

            return $this->find($bill->id);
        });
    }

    protected function calculateBillFinancials(array $data): array
    {
        $calculatedItems = [];
        $subtotal = 0.0;
        $itemsTaxAmount = 0.0;

        foreach ($data['items'] as $item) {
            $quantity = (float) $item['quantity'];
            $unitPrice = (float) $item['unit_price'];
            $lineGross = round($quantity * $unitPrice, 4);

            $discountPercentage = isset($item['discount_percentage']) ? (float) $item['discount_percentage'] : 0.0;
            $discountAmount = round($lineGross * ($discountPercentage / 100), 4);
            $lineSubtotal = round($lineGross - $discountAmount, 4);

            $taxRate = isset($item['tax_rate']) ? (float) $item['tax_rate'] : 0.0;
            $taxAmount = round($lineSubtotal * ($taxRate / 100), 4);
            $lineTotal = round($lineSubtotal + $taxAmount, 4);

            $subtotal += $lineSubtotal;
            $itemsTaxAmount += $taxAmount;

            $itemRecord = $item;
            $itemRecord['quantity'] = $quantity;
            $itemRecord['unit_price'] = $unitPrice;
            $itemRecord['discount_percentage'] = $discountPercentage;
            $itemRecord['discount_amount'] = $discountAmount;
            $itemRecord['tax_rate'] = $taxRate;
            $itemRecord['tax_amount'] = $taxAmount;
            $itemRecord['subtotal'] = $lineSubtotal;
            $itemRecord['total'] = $lineTotal;

            $calculatedItems[] = $itemRecord;
        }

        $discountType = $data['discount_type'] ?? 'fixed';
        $discountValue = isset($data['discount_value']) ? (float) $data['discount_value'] : 0.0;
        $billDiscountAmount = 0.0;

        if ($discountType === 'percentage') {
            $billDiscountAmount = round($subtotal * ($discountValue / 100), 4);
        } else {
            $billDiscountAmount = round($discountValue, 4);
        }

        $shippingCost = isset($data['shipping_cost']) ? (float) $data['shipping_cost'] : 0.0;
        $totalAmount = round(($subtotal - $billDiscountAmount) + $itemsTaxAmount + $shippingCost, 4);

        return [
            'items' => $calculatedItems,
            'subtotal' => round($subtotal, 4),
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'discount_amount' => $billDiscountAmount,
            'tax_amount' => round($itemsTaxAmount, 4),
            'shipping_cost' => round($shippingCost, 4),
            'total_amount' => max(0.0, $totalAmount),
        ];
    }

    protected function generateBillNumber(): string
    {
        $prefix = 'BILL-' . Carbon::now()->format('Ymd');

        $lastRecord = PurchaseBill::withTrashed()
            ->where('bill_number', 'like', "{$prefix}-%")
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if ($lastRecord && preg_match('/-(\d{4})$/', $lastRecord->bill_number, $matches)) {
            $nextSequence = (int) $matches[1] + 1;
        } else {
            $nextSequence = 1;
        }

        return sprintf('%s-%04d', $prefix, $nextSequence);
    }

    protected function incrementOrderBilledQuantity(int $orderItemId, float $quantity, int $userId): void
    {
        $orderItem = PurchaseOrderItem::lockForUpdate()->find($orderItemId);
        if ($orderItem) {
            $orderItem->increment('billed_quantity', $quantity, ['updated_by' => $userId]);
        }
    }

    protected function decrementOrderBilledQuantity(int $orderItemId, float $quantity, int $userId): void
    {
        $orderItem = PurchaseOrderItem::lockForUpdate()->find($orderItemId);
        if ($orderItem) {
            $newQuantity = max(0.0, (float) $orderItem->billed_quantity - $quantity);
            $orderItem->update([
                'billed_quantity' => $newQuantity,
                'updated_by' => $userId,
            ]);
        }
    }

    protected function incrementOrderReceivedQuantity(int $orderItemId, float $quantity, int $userId): void
    {
        $orderItem = PurchaseOrderItem::lockForUpdate()->find($orderItemId);
        if ($orderItem) {
            $orderItem->increment('received_quantity', $quantity, ['updated_by' => $userId]);
        }
    }

    protected function decrementOrderReceivedQuantity(int $orderItemId, float $quantity, int $userId): void
    {
        $orderItem = PurchaseOrderItem::lockForUpdate()->find($orderItemId);
        if ($orderItem) {
            $newQuantity = max(0.0, (float) $orderItem->received_quantity - $quantity);
            $orderItem->update([
                'received_quantity' => $newQuantity,
                'updated_by' => $userId,
            ]);
        }
    }

    protected function syncPurchaseOrderStatus(int $purchaseOrderId, int $userId): void
    {
        $order = PurchaseOrder::with('items')->find($purchaseOrderId);
        if (! $order || in_array($order->status, [PurchaseOrderStatus::CANCELLED, PurchaseOrderStatus::CLOSED], true)) {
            return;
        }

        $allBilled = $order->items->every(function ($item) {
            return (float) $item->billed_quantity >= (float) $item->quantity;
        });

        $anyBilled = $order->items->some(function ($item) {
            return (float) $item->billed_quantity > 0;
        });

        $allReceived = $order->items->every(function ($item) {
            return (float) $item->received_quantity >= (float) $item->quantity;
        });

        if ($allBilled && $allReceived) {
            $order->update([
                'status' => PurchaseOrderStatus::CLOSED,
                'updated_by' => $userId,
            ]);
        } elseif ($allBilled) {
            $order->update([
                'status' => PurchaseOrderStatus::BILLED,
                'updated_by' => $userId,
            ]);
        } elseif ($anyBilled) {
            $order->update([
                'status' => PurchaseOrderStatus::PARTIALLY_BILLED,
                'updated_by' => $userId,
            ]);
        }
    }
}