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
use InvalidArgumentException;
use RuntimeException;

class PurchaseOrderService
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = PurchaseOrder::query()
            ->with([
                'supplier',
                'currency',
                'confirmer',
            ])
            ->withCount('items');

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('order_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhere('terms_and_conditions', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
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
            'requisition',
            'currency',
            'confirmer',
            'creator',
            'updater',
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
                'supplier_id' => $data['supplier_id'],
                'requisition_id' => $data['requisition_id'] ?? null,
                'currency_id' => $data['currency_id'],
                'exchange_rate' => $data['exchange_rate'] ?? 1.0000,
                'order_date' => $data['order_date'] ?? Carbon::now()->toDateString(),
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'payment_terms' => $data['payment_terms'] ?? null,
                'status' => PurchaseOrderStatus::DRAFT,
                'subtotal' => $calculatedData['subtotal'],
                'discount_type' => $calculatedData['discount_type'],
                'discount_value' => $calculatedData['discount_value'],
                'discount_amount' => $calculatedData['discount_amount'],
                'tax_amount' => $calculatedData['tax_amount'],
                'shipping_cost' => $calculatedData['shipping_cost'],
                'total_amount' => $calculatedData['total_amount'],
                'notes' => $data['notes'] ?? null,
                'terms_and_conditions' => $data['terms_and_conditions'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            foreach ($calculatedData['items'] as $itemData) {
                $order->items()->create([
                    'requisition_item_id' => $itemData['requisition_item_id'] ?? null,
                    'product_id' => $itemData['product_id'] ?? null,
                    'item_name' => $itemData['item_name'] ?? null,
                    'product_unit_id' => $itemData['product_unit_id'] ?? null,
                    'quantity' => $itemData['quantity'],
                    'received_quantity' => 0.0000,
                    'billed_quantity' => 0.0000,
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

                if (! empty($itemData['requisition_item_id'])) {
                    $this->incrementRequisitionOrderedQuantity((int) $itemData['requisition_item_id'], (float) $itemData['quantity'], $userId);
                }
            }

            if (! empty($data['requisition_id'])) {
                $this->syncRequisitionStatus((int) $data['requisition_id'], $userId);
            }

            return $this->find($order->id);
        });
    }

    public function update(PurchaseOrder $order, array $data, int $userId): PurchaseOrder
    {
        if ($order->status !== PurchaseOrderStatus::DRAFT) {
            throw new RuntimeException('لا يمكن تعديل أمر الشراء إلا إذا كان في حالة مسودة.');
        }

        return DB::transaction(function () use ($order, $data, $userId): PurchaseOrder {
            $calculatedData = $this->calculateOrderFinancials($data);

            $order->update([
                'supplier_id' => $data['supplier_id'],
                'currency_id' => $data['currency_id'],
                'exchange_rate' => $data['exchange_rate'] ?? $order->exchange_rate,
                'order_date' => $data['order_date'] ?? $order->order_date,
                'expected_delivery_date' => array_key_exists('expected_delivery_date', $data) ? $data['expected_delivery_date'] : $order->expected_delivery_date,
                'payment_terms' => array_key_exists('payment_terms', $data) ? $data['payment_terms'] : $order->payment_terms,
                'subtotal' => $calculatedData['subtotal'],
                'discount_type' => $calculatedData['discount_type'],
                'discount_value' => $calculatedData['discount_value'],
                'discount_amount' => $calculatedData['discount_amount'],
                'tax_amount' => $calculatedData['tax_amount'],
                'shipping_cost' => $calculatedData['shipping_cost'],
                'total_amount' => $calculatedData['total_amount'],
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $order->notes,
                'terms_and_conditions' => array_key_exists('terms_and_conditions', $data) ? $data['terms_and_conditions'] : $order->terms_and_conditions,
                'updated_by' => $userId,
            ]);

            $existingItems = $order->items()->get()->keyBy('id');
            $submittedItemIds = collect($calculatedData['items'])->pluck('id')->filter()->map(fn ($id) => (int) $id)->toArray();

            foreach ($existingItems as $existingId => $existingItem) {
                if (! in_array($existingId, $submittedItemIds, true)) {
                    if ($existingItem->requisition_item_id) {
                        $this->decrementRequisitionOrderedQuantity(
                            (int) $existingItem->requisition_item_id,
                            (float) $existingItem->quantity,
                            $userId
                        );
                    }
                    $existingItem->delete();
                }
            }

            foreach ($calculatedData['items'] as $itemData) {
                if (! empty($itemData['id']) && $existingItems->has($itemData['id'])) {
                    /** @var PurchaseOrderItem $existingItem */
                    $existingItem = $existingItems->get($itemData['id']);
                    $quantityDiff = (float) $itemData['quantity'] - (float) $existingItem->quantity;

                    $existingItem->update([
                        'requisition_item_id' => $itemData['requisition_item_id'] ?? $existingItem->requisition_item_id,
                        'product_id' => $itemData['product_id'] ?? null,
                        'item_name' => $itemData['item_name'] ?? $existingItem->item_name,
                        'product_unit_id' => $itemData['product_unit_id'] ?? null,
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

                    if ($existingItem->requisition_item_id && $quantityDiff != 0.0) {
                        if ($quantityDiff > 0) {
                            $this->incrementRequisitionOrderedQuantity((int) $existingItem->requisition_item_id, $quantityDiff, $userId);
                        } else {
                            $this->decrementRequisitionOrderedQuantity((int) $existingItem->requisition_item_id, abs($quantityDiff), $userId);
                        }
                    }
                } else {
                    $order->items()->create([
                        'requisition_item_id' => $itemData['requisition_item_id'] ?? null,
                        'product_id' => $itemData['product_id'] ?? null,
                        'item_name' => $itemData['item_name'] ?? null,
                        'product_unit_id' => $itemData['product_unit_id'] ?? null,
                        'quantity' => $itemData['quantity'],
                        'received_quantity' => 0.0000,
                        'billed_quantity' => 0.0000,
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

                    if (! empty($itemData['requisition_item_id'])) {
                        $this->incrementRequisitionOrderedQuantity((int) $itemData['requisition_item_id'], (float) $itemData['quantity'], $userId);
                    }
                }
            }

            if ($order->requisition_id) {
                $this->syncRequisitionStatus((int) $order->requisition_id, $userId);
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
            foreach ($order->items as $item) {
                if ($item->requisition_item_id) {
                    $this->decrementRequisitionOrderedQuantity(
                        (int) $item->requisition_item_id,
                        (float) $item->quantity,
                        (int) $order->updated_by
                    );
                }
                $item->delete();
            }

            if ($order->requisition_id) {
                $this->syncRequisitionStatus((int) $order->requisition_id, (int) $order->updated_by);
            }

            return (bool) $order->delete();
        });
    }

    public function confirm(PurchaseOrder $order, int $userId): PurchaseOrder
    {
        if ($order->status !== PurchaseOrderStatus::DRAFT) {
            throw new RuntimeException('لا يمكن تأكيد أمر الشراء إلا إذا كان في حالة مسودة.');
        }

        if ($order->items()->count() === 0) {
            throw new RuntimeException('لا يمكن تأكيد أمر شراء بدون بنود.');
        }

        $order->update([
            'status' => PurchaseOrderStatus::CONFIRMED,
            'confirmed_by' => $userId,
            'confirmed_at' => Carbon::now(),
            'updated_by' => $userId,
        ]);

        return $this->find($order->id);
    }

    public function cancel(PurchaseOrder $order, int $userId): PurchaseOrder
    {
        if (! in_array($order->status, [PurchaseOrderStatus::DRAFT, PurchaseOrderStatus::CONFIRMED], true)) {
            throw new RuntimeException('لا يمكن إلغاء أمر شراء تم استلام بضائع منه أو تمت فوترته.');
        }

        return DB::transaction(function () use ($order, $userId): PurchaseOrder {
            foreach ($order->items as $item) {
                if ($item->requisition_item_id) {
                    $this->decrementRequisitionOrderedQuantity(
                        (int) $item->requisition_item_id,
                        (float) $item->quantity,
                        $userId
                    );
                }
            }

            $order->update([
                'status' => PurchaseOrderStatus::CANCELLED,
                'updated_by' => $userId,
            ]);

            if ($order->requisition_id) {
                $this->syncRequisitionStatus((int) $order->requisition_id, $userId);
            }

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