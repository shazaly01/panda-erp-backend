<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Services;

use App\Modules\Purchasing\Enums\PurchaseOrderStatus;
use App\Modules\Purchasing\Enums\RequisitionStatus;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseOrderItem;
use App\Modules\Purchasing\Models\PurchaseRequisition;
use App\Modules\Purchasing\Models\PurchaseRequisitionItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PurchaseOrderService
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = PurchaseOrder::query()
            ->with([
                'supplier',
                'warehouse',
                'currency',
                'requisition',
                'creator',
                'confirmer',
            ])
            ->withCount('items');

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('order_number', 'like', "%{$search}%")
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
            $query->where('supplier_id', $filters['supplier_id']);
        }

        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (! empty($filters['currency_id'])) {
            $query->where('currency_id', $filters['currency_id']);
        }

        if (! empty($filters['requisition_id'])) {
            $query->where('requisition_id', $filters['requisition_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('order_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('order_date', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    public function find(int $id): PurchaseOrder
    {
        return PurchaseOrder::with([
            'supplier',
            'warehouse',
            'currency',
            'requisition',
            'creator',
            'updater',
            'confirmer',
            'receipts',
            'bills',
            'items.product',
            'items.productUnit',
            'items.requisitionItem',
            'items.creator',
            'items.updater',
        ])->findOrFail($id);
    }

    public function create(array $data, int $userId): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $userId): PurchaseOrder {
            $orderNumber = $this->generateOrderNumber();
            $calculatedData = $this->calculateOrderFinancials($data);

            $order = PurchaseOrder::create([
                'order_number' => $orderNumber,
                'requisition_id' => $data['requisition_id'] ?? null,
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'currency_id' => $data['currency_id'],
                'exchange_rate' => $data['exchange_rate'] ?? 1.0000,
                'order_date' => $data['order_date'] ?? Carbon::now()->toDateString(),
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'status' => PurchaseOrderStatus::DRAFT,
                'subtotal' => $calculatedData['subtotal'],
                'discount_type' => $calculatedData['discount_type'],
                'discount_value' => $calculatedData['discount_value'],
                'discount_amount' => $calculatedData['discount_amount'],
                'tax_amount' => $calculatedData['tax_amount'],
                'shipping_cost' => $calculatedData['shipping_cost'],
                'total_amount' => $calculatedData['total_amount'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            foreach ($calculatedData['items'] as $itemData) {
                $order->items()->create([
                    'requisition_item_id' => $itemData['requisition_item_id'] ?? null,
                    'product_id' => $itemData['product_id'],
                    'product_unit_id' => $itemData['product_unit_id'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'discount_percentage' => $itemData['discount_percentage'],
                    'discount_amount' => $itemData['discount_amount'],
                    'tax_rate' => $itemData['tax_rate'],
                    'tax_amount' => $itemData['tax_amount'],
                    'subtotal' => $itemData['subtotal'],
                    'total' => $itemData['total'],
                    'received_quantity' => 0.0000,
                    'billed_quantity' => 0.0000,
                    'notes' => $itemData['notes'] ?? null,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }

            return $this->find($order->id);
        });
    }

    public function update(PurchaseOrder $order, array $data, int $userId): PurchaseOrder
    {
        if (! in_array($order->status, [PurchaseOrderStatus::DRAFT, PurchaseOrderStatus::CONFIRMED], true)) {
            throw new RuntimeException('لا يمكن تعديل أمر الشراء إلا إذا كان في حالة مسودة أو مؤكد.');
        }

        $calculatedData = $this->calculateOrderFinancials($data);

        return DB::transaction(function () use ($order, $data, $userId, $calculatedData): PurchaseOrder {
            $isConfirmed = ($order->status === PurchaseOrderStatus::CONFIRMED);
            $oldRequisitionId = $order->requisition_id;

            $existingItems = $order->items()->get()->keyBy('id');
            $submittedItemIds = collect($calculatedData['items'])
                ->pluck('id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->toArray();

            // 1. صمام الأمان: التحقق من عدم حذف أو تنزيل كميات بنود تم استلامها أو فوترتها بالفعل
            if ($isConfirmed) {
                foreach ($existingItems as $existingItem) {
                    $minQuantity = max((float) $existingItem->received_quantity, (float) $existingItem->billed_quantity);

                    if ($minQuantity > 0.0) {
                        if (! in_array($existingItem->id, $submittedItemIds, true)) {
                            throw new RuntimeException("لا يمكن حذف أحد بنود أمر الشراء لوجود كميات مستلمة أو مفوترة منه مسبقاً ({$minQuantity}).");
                        }

                        $submittedItem = collect($calculatedData['items'])->firstWhere('id', $existingItem->id);
                        if ($submittedItem && (float) $submittedItem['quantity'] < $minQuantity) {
                            throw new RuntimeException("لا يمكن تقليل كمية البند إلى أقل من الكمية المستلمة أو المفوترة فعلياً ({$minQuantity}).");
                        }
                    }

                    // عكس الكميات المحجوزة من طلب الاحتياج للبنود القديمة
                    if ($existingItem->requisition_item_id && (float) $existingItem->quantity > 0) {
                        $this->decrementRequisitionOrderedQuantity(
                            (int) $existingItem->requisition_item_id,
                            (float) $existingItem->quantity,
                            $userId
                        );
                    }
                }
            }

            // 2. تحديث رأس أمر الشراء
            $order->update([
                'requisition_id' => array_key_exists('requisition_id', $data) ? $data['requisition_id'] : $order->requisition_id,
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => array_key_exists('warehouse_id', $data) ? $data['warehouse_id'] : $order->warehouse_id,
                'currency_id' => $data['currency_id'],
                'exchange_rate' => $data['exchange_rate'] ?? $order->exchange_rate,
                'order_date' => $data['order_date'] ?? $order->order_date,
                'expected_delivery_date' => array_key_exists('expected_delivery_date', $data) ? $data['expected_delivery_date'] : $order->expected_delivery_date,
                'subtotal' => $calculatedData['subtotal'],
                'discount_type' => $calculatedData['discount_type'],
                'discount_value' => $calculatedData['discount_value'],
                'discount_amount' => $calculatedData['discount_amount'],
                'tax_amount' => $calculatedData['tax_amount'],
                'shipping_cost' => $calculatedData['shipping_cost'],
                'total_amount' => $calculatedData['total_amount'],
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $order->notes,
                'updated_by' => $userId,
            ]);

            // 3. مزامنة بنود أمر الشراء (حذف، تعديل، إضافة)
            foreach ($existingItems as $existingId => $existingItem) {
                if (! in_array($existingId, $submittedItemIds, true)) {
                    $existingItem->delete();
                }
            }

            foreach ($calculatedData['items'] as $itemData) {
                if (! empty($itemData['id']) && $existingItems->has($itemData['id'])) {
                    /** @var PurchaseOrderItem $existingItem */
                    $existingItem = $existingItems->get($itemData['id']);
                    $existingItem->update([
                        'requisition_item_id' => $itemData['requisition_item_id'] ?? $existingItem->requisition_item_id,
                        'product_id' => $itemData['product_id'],
                        'product_unit_id' => $itemData['product_unit_id'],
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
                    $order->items()->create([
                        'requisition_item_id' => $itemData['requisition_item_id'] ?? null,
                        'product_id' => $itemData['product_id'],
                        'product_unit_id' => $itemData['product_unit_id'],
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                        'discount_percentage' => $itemData['discount_percentage'],
                        'discount_amount' => $itemData['discount_amount'],
                        'tax_rate' => $itemData['tax_rate'],
                        'tax_amount' => $itemData['tax_amount'],
                        'subtotal' => $itemData['subtotal'],
                        'total' => $itemData['total'],
                        'received_quantity' => 0.0000,
                        'billed_quantity' => 0.0000,
                        'notes' => $itemData['notes'] ?? null,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);
                }
            }

            // 4. في حال كان الأمر مؤكداً: إعادة قيد الكميات في طلب الاحتياج ومزامنة حالته
            if ($isConfirmed) {
                $order->loadMissing('items');

                foreach ($order->items as $item) {
                    if ($item->requisition_item_id && (float) $item->quantity > 0) {
                        $this->incrementRequisitionOrderedQuantity(
                            (int) $item->requisition_item_id,
                            (float) $item->quantity,
                            $userId
                        );
                    }
                }

                if ($oldRequisitionId && $oldRequisitionId !== $order->requisition_id) {
                    $this->syncRequisitionStatus((int) $oldRequisitionId, $userId);
                }

                if ($order->requisition_id) {
                    $this->syncRequisitionStatus((int) $order->requisition_id, $userId);
                }
            }

            return $this->find($order->id);
        });
    }

    public function delete(PurchaseOrder $order): bool
    {
        if ($order->status !== PurchaseOrderStatus::DRAFT) {
            throw new RuntimeException('لا يمكن حذف أمر الشراء إلا إذا كان في حالة مسودة.');
        }

        return DB::transaction(function () use ($order): bool {
            $order->items()->delete();
            return (bool) $order->delete();
        });
    }

    public function confirm(PurchaseOrder $order, int $userId): PurchaseOrder
    {
        if ($order->status !== PurchaseOrderStatus::DRAFT) {
            throw new RuntimeException('لا يمكن تأكيد أمر الشراء إلا إذا كان في حالة مسودة.');
        }

        if ($order->items()->count() === 0) {
            throw new RuntimeException('لا يمكن تأكيد أمر شراء لا يحتوي على أي بنود.');
        }

        return DB::transaction(function () use ($order, $userId): PurchaseOrder {
            $order->loadMissing('items');

            foreach ($order->items as $item) {
                if ($item->requisition_item_id && (float) $item->quantity > 0) {
                    $this->incrementRequisitionOrderedQuantity(
                        (int) $item->requisition_item_id,
                        (float) $item->quantity,
                        $userId
                    );
                }
            }

            if ($order->requisition_id) {
                $this->syncRequisitionStatus((int) $order->requisition_id, $userId);
            }

            $order->update([
                'status' => PurchaseOrderStatus::CONFIRMED,
                'confirmed_by' => $userId,
                'confirmed_at' => Carbon::now(),
                'updated_by' => $userId,
            ]);

            return $this->find($order->id);
        });
    }

    public function cancel(PurchaseOrder $order, int $userId): PurchaseOrder
    {
        if ($order->status === PurchaseOrderStatus::CANCELLED) {
            throw new RuntimeException('أمر الشراء ملغي بالفعل.');
        }

        if ($order->status === PurchaseOrderStatus::CLOSED) {
            throw new RuntimeException('لا يمكن إلغاء أمر شراء مغلق.');
        }

        $order->loadMissing('items');

        $hasMovements = $order->items->some(function ($item) {
            return (float) $item->received_quantity > 0 || (float) $item->billed_quantity > 0;
        });

        if ($hasMovements) {
            throw new RuntimeException('لا يمكن إلغاء أمر الشراء لوجود كميات مستلمة أو مفوترة منه بالفعل.');
        }

        return DB::transaction(function () use ($order, $userId): PurchaseOrder {
            if ($order->status === PurchaseOrderStatus::CONFIRMED) {
                foreach ($order->items as $item) {
                    if ($item->requisition_item_id && (float) $item->quantity > 0) {
                        $this->decrementRequisitionOrderedQuantity(
                            (int) $item->requisition_item_id,
                            (float) $item->quantity,
                            $userId
                        );
                    }
                }

                if ($order->requisition_id) {
                    $this->syncRequisitionStatus((int) $order->requisition_id, $userId);
                }
            }

            $order->update([
                'status' => PurchaseOrderStatus::CANCELLED,
                'updated_by' => $userId,
            ]);

            return $this->find($order->id);
        });
    }

    protected function calculateOrderFinancials(array $data): array
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
        $orderDiscountAmount = 0.0;

        if ($discountType === 'percentage') {
            $orderDiscountAmount = round($subtotal * ($discountValue / 100), 4);
        } else {
            $orderDiscountAmount = round($discountValue, 4);
        }

        $shippingCost = isset($data['shipping_cost']) ? (float) $data['shipping_cost'] : 0.0;
        $totalAmount = round(($subtotal - $orderDiscountAmount) + $itemsTaxAmount + $shippingCost, 4);

        return [
            'items' => $calculatedItems,
            'subtotal' => round($subtotal, 4),
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'discount_amount' => $orderDiscountAmount,
            'tax_amount' => round($itemsTaxAmount, 4),
            'shipping_cost' => round($shippingCost, 4),
            'total_amount' => max(0.0, $totalAmount),
        ];
    }

    protected function generateOrderNumber(): string
    {
        $prefix = 'PO-' . Carbon::now()->format('Ymd');

        $lastRecord = PurchaseOrder::withTrashed()
            ->where('order_number', 'like', "{$prefix}-%")
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if ($lastRecord && preg_match('/-(\d{4})$/', $lastRecord->order_number, $matches)) {
            $nextSequence = (int) $matches[1] + 1;
        } else {
            $nextSequence = 1;
        }

        return sprintf('%s-%04d', $prefix, $nextSequence);
    }

    protected function incrementRequisitionOrderedQuantity(int $requisitionItemId, float $quantity, int $userId): void
    {
        $reqItem = PurchaseRequisitionItem::lockForUpdate()->find($requisitionItemId);
        if ($reqItem) {
            $reqItem->increment('quantity_ordered', $quantity, ['updated_by' => $userId]);
        }
    }

    protected function decrementRequisitionOrderedQuantity(int $requisitionItemId, float $quantity, int $userId): void
    {
        $reqItem = PurchaseRequisitionItem::lockForUpdate()->find($requisitionItemId);
        if ($reqItem) {
            $newQuantity = max(0.0, (float) $reqItem->quantity_ordered - $quantity);
            $reqItem->update([
                'quantity_ordered' => $newQuantity,
                'updated_by' => $userId,
            ]);
        }
    }

    protected function syncRequisitionStatus(int $requisitionId, int $userId): void
    {
        $requisition = PurchaseRequisition::with('items')->find($requisitionId);
        if (! $requisition || in_array($requisition->status, [RequisitionStatus::CANCELLED, RequisitionStatus::REJECTED], true)) {
            return;
        }

        $allFulfilled = $requisition->items->every(function ($item): bool {
            $targetQty = (float) ($item->quantity_approved > 0 ? $item->quantity_approved : $item->quantity_requested);
            $totalFulfilled = (float) $item->quantity_ordered + (float) $item->quantity_issued;
            return $totalFulfilled >= $targetQty;
        });

        if ($allFulfilled && $requisition->status !== RequisitionStatus::ORDERED) {
            $requisition->update([
                'status' => RequisitionStatus::ORDERED,
                'updated_by' => $userId,
            ]);
        } elseif (! $allFulfilled && $requisition->status === RequisitionStatus::ORDERED) {
            $requisition->update([
                'status' => RequisitionStatus::APPROVED,
                'updated_by' => $userId,
            ]);
        }
    }
}