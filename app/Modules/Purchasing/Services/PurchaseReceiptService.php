<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Services;

use App\Modules\Inventory\Services\StockMovementService;
use App\Modules\Purchasing\Enums\PurchaseOrderStatus;
use App\Modules\Purchasing\Enums\ReceiptStatus;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseOrderItem;
use App\Modules\Purchasing\Models\PurchaseReceipt;
use App\Modules\Purchasing\Models\PurchaseReceiptItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PurchaseReceiptService
{
    public function __construct(
        protected readonly StockMovementService $stockMovementService
    ) {}

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = PurchaseReceipt::query()
            ->with([
                'supplier',
                'warehouse',
                'purchaseOrder',
                'receiver',
            ])
            ->withCount('items');

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('receipt_number', 'like', "%{$search}%")
                    ->orWhere('supplier_delivery_note', 'like', "%{$search}%")
                    ->orWhere('waybill_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
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

        if (! empty($filters['purchase_order_id'])) {
            $query->where('purchase_order_id', $filters['purchase_order_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('receipt_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('receipt_date', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    public function find(int $id): PurchaseReceipt
    {
        return PurchaseReceipt::with([
            'supplier',
            'warehouse',
            'purchaseOrder',
            'receiver',
            'creator',
            'updater',
            'stockMovements',
            'items.product',
            'items.productUnit',
            'items.location',
            'items.batch',
            'items.orderItem',
            'items.creator',
            'items.updater',
        ])->findOrFail($id);
    }

    public function create(array $data, int $userId): PurchaseReceipt
    {
        return DB::transaction(function () use ($data, $userId): PurchaseReceipt {
            $receiptNumber = $this->generateReceiptNumber();

            $receipt = PurchaseReceipt::create([
                'receipt_number' => $receiptNumber,
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => $data['warehouse_id'],
                'receipt_date' => $data['receipt_date'] ?? Carbon::now()->toDateString(),
                'status' => ReceiptStatus::DRAFT,
                'supplier_delivery_note' => $data['supplier_delivery_note'] ?? null,
                'waybill_number' => $data['waybill_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            foreach ($data['items'] as $itemData) {
                $quantityReceived = (float) $itemData['quantity_received'];
                $quantityAccepted = (float) ($itemData['quantity_accepted'] ?? $quantityReceived);
                $quantityRejected = isset($itemData['quantity_rejected'])
                    ? (float) $itemData['quantity_rejected']
                    : max(0.0, round($quantityReceived - $quantityAccepted, 4));

                $unitCost = (float) ($itemData['unit_cost'] ?? 0.0000);
                $totalCost = round($quantityAccepted * $unitCost, 4);

                $receipt->items()->create([
                    'purchase_order_item_id' => $itemData['purchase_order_item_id'] ?? null,
                    'product_id' => $itemData['product_id'],
                    'product_unit_id' => $itemData['product_unit_id'],
                    'location_id' => $itemData['location_id'] ?? null,
                    'batch_id' => $itemData['batch_id'] ?? null,
                    'quantity_received' => $quantityReceived,
                    'quantity_accepted' => $quantityAccepted,
                    'quantity_rejected' => $quantityRejected,
                    'unit_cost' => $unitCost,
                    'total_cost' => $totalCost,
                    'rejection_reason' => $itemData['rejection_reason'] ?? null,
                    'notes' => $itemData['notes'] ?? null,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }

            return $this->find($receipt->id);
        });
    }

    public function update(PurchaseReceipt $receipt, array $data, int $userId): PurchaseReceipt
    {
        if ($receipt->status !== ReceiptStatus::DRAFT) {
            throw new RuntimeException('لا يمكن تعديل سند الاستلام إلا إذا كان في حالة مسودة.');
        }

        return DB::transaction(function () use ($receipt, $data, $userId): PurchaseReceipt {
            $receipt->update([
                'purchase_order_id' => array_key_exists('purchase_order_id', $data) ? $data['purchase_order_id'] : $receipt->purchase_order_id,
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => $data['warehouse_id'],
                'receipt_date' => $data['receipt_date'] ?? $receipt->receipt_date,
                'supplier_delivery_note' => array_key_exists('supplier_delivery_note', $data) ? $data['supplier_delivery_note'] : $receipt->supplier_delivery_note,
                'waybill_number' => array_key_exists('waybill_number', $data) ? $data['waybill_number'] : $receipt->waybill_number,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $receipt->notes,
                'updated_by' => $userId,
            ]);

            $existingItems = $receipt->items()->get()->keyBy('id');
            $submittedItemIds = collect($data['items'])->pluck('id')->filter()->map(fn ($id) => (int) $id)->toArray();

            foreach ($existingItems as $existingId => $existingItem) {
                if (! in_array($existingId, $submittedItemIds, true)) {
                    $existingItem->delete();
                }
            }

            foreach ($data['items'] as $itemData) {
                $quantityReceived = (float) $itemData['quantity_received'];
                $quantityAccepted = (float) ($itemData['quantity_accepted'] ?? $quantityReceived);
                $quantityRejected = isset($itemData['quantity_rejected'])
                    ? (float) $itemData['quantity_rejected']
                    : max(0.0, round($quantityReceived - $quantityAccepted, 4));

                $unitCost = (float) ($itemData['unit_cost'] ?? 0.0000);
                $totalCost = round($quantityAccepted * $unitCost, 4);

                if (! empty($itemData['id']) && $existingItems->has($itemData['id'])) {
                    /** @var PurchaseReceiptItem $existingItem */
                    $existingItem = $existingItems->get($itemData['id']);
                    $existingItem->update([
                        'purchase_order_item_id' => $itemData['purchase_order_item_id'] ?? $existingItem->purchase_order_item_id,
                        'product_id' => $itemData['product_id'],
                        'product_unit_id' => $itemData['product_unit_id'],
                        'location_id' => $itemData['location_id'] ?? null,
                        'batch_id' => $itemData['batch_id'] ?? null,
                        'quantity_received' => $quantityReceived,
                        'quantity_accepted' => $quantityAccepted,
                        'quantity_rejected' => $quantityRejected,
                        'unit_cost' => $unitCost,
                        'total_cost' => $totalCost,
                        'rejection_reason' => $itemData['rejection_reason'] ?? null,
                        'notes' => $itemData['notes'] ?? null,
                        'updated_by' => $userId,
                    ]);
                } else {
                    $receipt->items()->create([
                        'purchase_order_item_id' => $itemData['purchase_order_item_id'] ?? null,
                        'product_id' => $itemData['product_id'],
                        'product_unit_id' => $itemData['product_unit_id'],
                        'location_id' => $itemData['location_id'] ?? null,
                        'batch_id' => $itemData['batch_id'] ?? null,
                        'quantity_received' => $quantityReceived,
                        'quantity_accepted' => $quantityAccepted,
                        'quantity_rejected' => $quantityRejected,
                        'unit_cost' => $unitCost,
                        'total_cost' => $totalCost,
                        'rejection_reason' => $itemData['rejection_reason'] ?? null,
                        'notes' => $itemData['notes'] ?? null,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);
                }
            }

            return $this->find($receipt->id);
        });
    }

    public function delete(PurchaseReceipt $receipt): bool
    {
        if ($receipt->status !== ReceiptStatus::DRAFT) {
            throw new RuntimeException('لا يمكن حذف سند الاستلام إلا إذا كان في حالة مسودة.');
        }

        return DB::transaction(function () use ($receipt): bool {
            $receipt->items()->delete();
            return (bool) $receipt->delete();
        });
    }

    public function receive(PurchaseReceipt $receipt, int $userId): PurchaseReceipt
    {
        if ($receipt->status !== ReceiptStatus::DRAFT) {
            throw new RuntimeException('لا يمكن تأكيد استلام السند إلا إذا كان في حالة مسودة.');
        }

        if ($receipt->items()->count() === 0) {
            throw new RuntimeException('لا يمكن استلام سند لا يحتوي على أي بنود.');
        }

        return DB::transaction(function () use ($receipt, $userId): PurchaseReceipt {
            $receipt->loadMissing(['items', 'purchaseOrder.items']);

            foreach ($receipt->items as $item) {
                $acceptedQuantity = (float) $item->quantity_accepted;

                if ($acceptedQuantity > 0) {
                    $this->stockMovementService->recordMovement(
                        productId: (int) $item->product_id,
                        warehouseId: (int) $receipt->warehouse_id,
                        productUnitId: (int) $item->product_unit_id,
                        movementType: 'purchase',
                        quantity: $acceptedQuantity,
                        unitCost: (float) $item->unit_cost,
                        reference: $receipt,
                        locationId: $item->location_id ? (int) $item->location_id : null,
                        batchId: $item->batch_id ? (int) $item->batch_id : null,
                        notes: "استلام مشتريات بموجب السند رقم: {$receipt->receipt_number}",
                        userId: $userId
                    );
                }

                if ($item->purchase_order_item_id && $acceptedQuantity > 0) {
                    $this->incrementOrderReceivedQuantity(
                        (int) $item->purchase_order_item_id,
                        $acceptedQuantity,
                        $userId
                    );
                }
            }

            if ($receipt->purchase_order_id) {
                $this->syncPurchaseOrderStatus((int) $receipt->purchase_order_id, $userId);
            }

            $receipt->update([
                'status' => ReceiptStatus::RECEIVED,
                'received_by' => $userId,
                'received_at' => Carbon::now(),
                'updated_by' => $userId,
            ]);

            return $this->find($receipt->id);
        });
    }

    public function cancel(PurchaseReceipt $receipt, int $userId): PurchaseReceipt
    {
        if ($receipt->status === ReceiptStatus::CANCELLED) {
            throw new RuntimeException('هذا السند ملغي بالفعل.');
        }

        return DB::transaction(function () use ($receipt, $userId): PurchaseReceipt {
            $receipt->loadMissing('items');

            if ($receipt->status === ReceiptStatus::RECEIVED) {
                $this->stockMovementService->clearDocumentMovements($receipt);

                foreach ($receipt->items as $item) {
                    $acceptedQuantity = (float) $item->quantity_accepted;
                    if ($item->purchase_order_item_id && $acceptedQuantity > 0) {
                        $this->decrementOrderReceivedQuantity(
                            (int) $item->purchase_order_item_id,
                            $acceptedQuantity,
                            $userId
                        );
                    }
                }

                if ($receipt->purchase_order_id) {
                    $this->syncPurchaseOrderStatus((int) $receipt->purchase_order_id, $userId);
                }
            }

            $receipt->update([
                'status' => ReceiptStatus::CANCELLED,
                'updated_by' => $userId,
            ]);

            return $this->find($receipt->id);
        });
    }

    protected function generateReceiptNumber(): string
    {
        $prefix = 'RC-' . Carbon::now()->format('Ymd');

        $lastRecord = PurchaseReceipt::withTrashed()
            ->where('receipt_number', 'like', "{$prefix}-%")
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if ($lastRecord && preg_match('/-(\d{4})$/', $lastRecord->receipt_number, $matches)) {
            $nextSequence = (int) $matches[1] + 1;
        } else {
            $nextSequence = 1;
        }

        return sprintf('%s-%04d', $prefix, $nextSequence);
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

        $allReceived = $order->items->every(function ($item) {
            return (float) $item->received_quantity >= (float) $item->quantity;
        });

        $anyReceived = $order->items->some(function ($item) {
            return (float) $item->received_quantity > 0;
        });

        if ($allReceived) {
            $order->update([
                'status' => PurchaseOrderStatus::RECEIVED,
                'updated_by' => $userId,
            ]);
        } elseif ($anyReceived) {
            $order->update([
                'status' => PurchaseOrderStatus::PARTIALLY_RECEIVED,
                'updated_by' => $userId,
            ]);
        } else {
            $order->update([
                'status' => PurchaseOrderStatus::CONFIRMED,
                'updated_by' => $userId,
            ]);
        }
    }
}