<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Services;

use App\Modules\Inventory\Enums\StockSerialStatus;
use App\Modules\Inventory\Models\StockSerial;
use App\Modules\Inventory\Services\StockMovementService;
use App\Modules\Purchasing\Enums\BillStatus;
use App\Modules\Purchasing\Enums\PurchaseOrderStatus;
use App\Modules\Purchasing\Enums\ReceiptStatus;
use App\Modules\Purchasing\Models\PurchaseBill;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseOrderItem;
use App\Modules\Purchasing\Models\PurchaseReceipt;
use App\Modules\Purchasing\Models\PurchaseReceiptItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PurchaseReceiptService
{
    public function __construct(
        protected readonly StockMovementService $stockMovementService
    ) {}

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = PurchaseReceipt::query()
            ->with(['supplier', 'warehouse', 'purchaseOrder', 'receiver'])
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

        foreach (['status', 'supplier_id', 'warehouse_id', 'purchase_order_id'] as $filter) {
            if (! empty($filters[$filter])) {
                $query->where($filter, $filters[$filter]);
            }
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
            'items.serials.serial',
            'items.serials.replacedSerial',
        ])->findOrFail($id);
    }

    public function create(array $data, int $userId): PurchaseReceipt
    {
        return DB::transaction(function () use ($data, $userId): PurchaseReceipt {
            $receipt = PurchaseReceipt::create([
                'receipt_number' => $this->generateReceiptNumber(),
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

                $receiptItem = $receipt->items()->create([
                    'purchase_order_item_id' => $itemData['purchase_order_item_id'] ?? null,
                    'product_id' => $itemData['product_id'],
                    'product_unit_id' => $itemData['product_unit_id'],
                    'location_id' => $itemData['location_id'] ?? null,
                    'batch_id' => $itemData['batch_id'] ?? null,
                    'quantity_received' => $quantityReceived,
                    'quantity_accepted' => $quantityAccepted,
                    'quantity_rejected' => $quantityRejected,
                    'unit_cost' => $unitCost,
                    'total_cost' => round($quantityAccepted * $unitCost, 4),
                    'rejection_reason' => $itemData['rejection_reason'] ?? null,
                    'notes' => $itemData['notes'] ?? null,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                $this->syncItemSerials(
                    $receiptItem,
                    (int) $itemData['product_id'],
                    $itemData['serials'] ?? []
                );
            }

            return $this->find((int) $receipt->id);
        });
    }

    public function update(PurchaseReceipt $receipt, array $data, int $userId): PurchaseReceipt
    {
        return DB::transaction(function () use ($receipt, $data, $userId): PurchaseReceipt {
            $receipt = PurchaseReceipt::query()->lockForUpdate()->findOrFail($receipt->getKey());

            if (! in_array($receipt->status, [ReceiptStatus::DRAFT, ReceiptStatus::RECEIVED], true)) {
                throw new RuntimeException('لا يمكن تعديل سند الاستلام إلا إذا كان في حالة مسودة أو مستلم.');
            }

            $isReceived = $receipt->status === ReceiptStatus::RECEIVED;
            $oldPurchaseOrderId = $receipt->purchase_order_id;
            $previousDocumentSerials = [];

            if ($isReceived) {
                $this->assertSerialReceiptCanBeReversed($receipt);
                $this->lockPurchaseOrders([$oldPurchaseOrderId, $data['purchase_order_id'] ?? $oldPurchaseOrderId]);

                if ($this->hasActiveBills($receipt)) {
                    throw new RuntimeException('لا يمكن تعديل سند الاستلام لوجود فاتورة شراء نشطة مرتبطة به. يرجى تعديل الفاتورة أو إلغاؤها أولاً.');
                }

                // حفظ نسخة من السيريالات السابقة لمعرفة ما إذا تم استبعاد أي منها أثناء التعديل
                $receipt->loadMissing('items.serials');
                foreach ($receipt->items as $item) {
                    foreach ($item->serials as $docSerial) {
                        if ($docSerial->serial_id) {
                            $previousDocumentSerials[(int) $docSerial->serial_id] = [
                                'previous_status' => $docSerial->previous_status,
                                'replaced_serial_id' => $docSerial->replaced_serial_id,
                            ];
                        }
                    }
                }

                // مسح الحركات المخزنية القديمة فقط دون حذف الأسطوانات
                $this->stockMovementService->clearDocumentMovements($receipt);

                // تخفيض كميات أمر الشراء القديمة لإعادة حسابها
                $receipt->load('items');
                foreach ($receipt->items as $oldItem) {
                    if ($oldItem->purchase_order_item_id && (float) $oldItem->quantity_accepted > 0) {
                        $this->decrementOrderReceivedQuantity(
                            (int) $oldItem->purchase_order_item_id,
                            (float) $oldItem->quantity_accepted,
                            $userId
                        );
                    }
                }
            }

            $receipt->update([
                'purchase_order_id' => array_key_exists('purchase_order_id', $data) ? $data['purchase_order_id'] : $receipt->purchase_order_id,
                'supplier_id' => $data['supplier_id'] ?? $receipt->supplier_id,
                'warehouse_id' => $data['warehouse_id'] ?? $receipt->warehouse_id,
                'receipt_date' => $data['receipt_date'] ?? $receipt->receipt_date,
                'supplier_delivery_note' => array_key_exists('supplier_delivery_note', $data) ? $data['supplier_delivery_note'] : $receipt->supplier_delivery_note,
                'waybill_number' => array_key_exists('waybill_number', $data) ? $data['waybill_number'] : $receipt->waybill_number,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $receipt->notes,
                'updated_by' => $userId,
            ]);

            if (array_key_exists('items', $data)) {
                $existingItems = $receipt->items()->get()->keyBy('id');
                $submittedItemIds = collect($data['items'])->pluck('id')->filter()
                    ->map(fn ($id) => (int) $id)->all();

                foreach ($submittedItemIds as $submittedId) {
                    if (! $existingItems->has($submittedId)) {
                        throw ValidationException::withMessages([
                            'items' => 'أحد معرفات البنود المرسلة لا ينتمي إلى سند الاستلام الحالي.',
                        ]);
                    }
                }

                foreach ($existingItems as $existingId => $existingItem) {
                    if (! in_array((int) $existingId, $submittedItemIds, true)) {
                        $existingItem->serials()->delete();
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
                    $existingItem = ! empty($itemData['id']) ? $existingItems->get((int) $itemData['id']) : null;

                    $attributes = [
                        'purchase_order_item_id' => array_key_exists('purchase_order_item_id', $itemData)
                            ? $itemData['purchase_order_item_id']
                            : $existingItem?->purchase_order_item_id,
                        'product_id' => $itemData['product_id'],
                        'product_unit_id' => $itemData['product_unit_id'],
                        'location_id' => $itemData['location_id'] ?? null,
                        'batch_id' => $itemData['batch_id'] ?? null,
                        'quantity_received' => $quantityReceived,
                        'quantity_accepted' => $quantityAccepted,
                        'quantity_rejected' => $quantityRejected,
                        'unit_cost' => $unitCost,
                        'total_cost' => round($quantityAccepted * $unitCost, 4),
                        'rejection_reason' => $itemData['rejection_reason'] ?? null,
                        'notes' => $itemData['notes'] ?? null,
                        'updated_by' => $userId,
                    ];

                    if ($existingItem) {
                        $existingItem->update($attributes);
                        $receiptItem = $existingItem;
                    } else {
                        $attributes['created_by'] = $userId;
                        $receiptItem = $receipt->items()->create($attributes);
                    }

                    if (array_key_exists('serials', $itemData) || ! $existingItem) {
                        $this->syncItemSerials(
                            $receiptItem,
                            (int) $itemData['product_id'],
                            $itemData['serials'] ?? []
                        );
                    }
                }
            }

            if ($isReceived) {
                $receipt->load(['items.product', 'items.productUnit', 'items.serials.serial']);
                $newSerialIds = [];

                foreach ($receipt->items as $item) {
                    $acceptedQuantity = (float) $item->quantity_accepted;
                    if ($acceptedQuantity > 0) {
                        $this->applyItemReceiptInventoryEffects($receipt, $item, $userId);
                    }
                    if ($item->purchase_order_item_id && $acceptedQuantity > 0) {
                        $this->incrementOrderReceivedQuantity((int) $item->purchase_order_item_id, $acceptedQuantity, $userId);
                    }

                    foreach ($item->serials as $docSerial) {
                        if ($docSerial->serial_id) {
                            $newSerialIds[(int) $docSerial->serial_id] = true;
                        }
                    }
                }

                // معالجة الأسطوانات التي استبعدها المستخدم من السند فقط أثناء التعديل
                foreach ($previousDocumentSerials as $oldSerialId => $oldMeta) {
                    if (! isset($newSerialIds[$oldSerialId])) {
                        $this->revertRemovedSerial((int) $oldSerialId, $oldMeta, (int) $receipt->warehouse_id, (string) $receipt->receipt_number);
                    }
                }

                if ($oldPurchaseOrderId && (int) $oldPurchaseOrderId !== (int) $receipt->purchase_order_id) {
                    $this->syncPurchaseOrderStatus((int) $oldPurchaseOrderId, $userId);
                }
                if ($receipt->purchase_order_id) {
                    $this->syncPurchaseOrderStatus((int) $receipt->purchase_order_id, $userId);
                }
            }

            return $this->find((int) $receipt->id);
        });
    }

    public function delete(PurchaseReceipt $receipt): bool
    {
        return DB::transaction(function () use ($receipt): bool {
            $receipt = PurchaseReceipt::query()->lockForUpdate()->findOrFail($receipt->getKey());
            if ($receipt->status !== ReceiptStatus::DRAFT) {
                throw new RuntimeException('لا يمكن حذف سند الاستلام إلا إذا كان في حالة مسودة.');
            }

            foreach ($receipt->items()->get() as $item) {
                $item->serials()->delete();
            }
            $receipt->items()->delete();

            return (bool) $receipt->delete();
        });
    }

    public function receive(PurchaseReceipt $receipt, int $userId): PurchaseReceipt
    {
        return DB::transaction(function () use ($receipt, $userId): PurchaseReceipt {
            $receipt = PurchaseReceipt::query()->lockForUpdate()->findOrFail($receipt->getKey());
            if ($receipt->status !== ReceiptStatus::DRAFT) {
                throw new RuntimeException('لا يمكن تأكيد استلام السند إلا إذا كان في حالة مسودة.');
            }

            $this->lockPurchaseOrders([$receipt->purchase_order_id]);
            $receipt->load(['items.product', 'items.productUnit', 'items.serials.serial']);
            if ($receipt->items->isEmpty()) {
                throw new RuntimeException('لا يمكن استلام سند لا يحتوي على أي بنود.');
            }

            foreach ($receipt->items as $item) {
                $acceptedQuantity = (float) $item->quantity_accepted;
                if ($acceptedQuantity > 0) {
                    $this->applyItemReceiptInventoryEffects($receipt, $item, $userId);
                }
                if ($item->purchase_order_item_id && $acceptedQuantity > 0) {
                    $this->incrementOrderReceivedQuantity((int) $item->purchase_order_item_id, $acceptedQuantity, $userId);
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

            return $this->find((int) $receipt->id);
        });
    }

    public function cancel(PurchaseReceipt $receipt, int $userId): PurchaseReceipt
    {
        return DB::transaction(function () use ($receipt, $userId): PurchaseReceipt {
            $receipt = PurchaseReceipt::query()->lockForUpdate()->findOrFail($receipt->getKey());
            if ($receipt->status === ReceiptStatus::CANCELLED) {
                throw new RuntimeException('هذا السند ملغي بالفعل.');
            }

            if ($receipt->status === ReceiptStatus::RECEIVED) {
                $this->assertSerialReceiptCanBeReversed($receipt);
                $this->lockPurchaseOrders([$receipt->purchase_order_id]);
                if ($this->hasActiveBills($receipt)) {
                    throw new RuntimeException('لا يمكن إلغاء سند الاستلام لوجود فاتورة شراء نشطة مرتبطة به. يرجى معالجة الفاتورة أولاً.');
                }

                $this->reverseReceiptSerials($receipt, $userId);
                $this->stockMovementService->clearDocumentMovements($receipt);

                foreach ($receipt->items()->get() as $item) {
                    if ($item->purchase_order_item_id && (float) $item->quantity_accepted > 0) {
                        $this->decrementOrderReceivedQuantity(
                            (int) $item->purchase_order_item_id,
                            (float) $item->quantity_accepted,
                            $userId
                        );
                    }
                }
                if ($receipt->purchase_order_id) {
                    $this->syncPurchaseOrderStatus((int) $receipt->purchase_order_id, $userId);
                }
            }

            $receipt->update(['status' => ReceiptStatus::CANCELLED, 'updated_by' => $userId]);

            return $this->find((int) $receipt->id);
        });
    }

    protected function syncItemSerials(PurchaseReceiptItem $receiptItem, int $productId, array $serialsData): void
    {
        $existingRows = $receiptItem->serials()->withTrashed()->with('serial')->get();
        $retainedIds = [];
        $seenIdentifiers = [];

        foreach ($serialsData as $serialEntry) {
            $serialId = ! empty($serialEntry['serial_id']) ? (int) $serialEntry['serial_id'] : null;
            $serialNumber = isset($serialEntry['serial_number']) ? trim((string) $serialEntry['serial_number']) : null;
            $serial = $this->resolveExistingSerial($productId, $serialId, $serialNumber);
            $serialNumber = $serial ? (string) $serial->serial_number : $serialNumber;

            if ($serialNumber === null || $serialNumber === '') {
                throw ValidationException::withMessages(['items' => 'يجب إدخال رقم الأسطوانة أو تحديد سيريال موجود.']);
            }

            $identifier = $serial ? 'id_' . $serial->id : 'num_' . mb_strtolower($serialNumber);
            if (isset($seenIdentifiers[$identifier])) {
                throw ValidationException::withMessages(['items' => "الأسطوانة ({$serialNumber}) مكررة في البند نفسه."]);
            }
            $seenIdentifiers[$identifier] = true;

            $tareWeight = $this->parseReceiptWeight($serialEntry['tare_weight'] ?? null, 'الوزن الفارغ');
            if ($tareWeight === null && $serial?->tare_weight !== null) {
                $tareWeight = (float) $serial->tare_weight;
            }
            $grossWeight = $this->parseReceiptWeight($serialEntry['gross_weight'] ?? null, 'الوزن القائم');
            $netWeight = $this->parseReceiptWeight($serialEntry['net_weight'] ?? null, 'صافي الوزن');
            if ($grossWeight !== null && $tareWeight !== null) {
                if ($grossWeight < $tareWeight) {
                    throw ValidationException::withMessages(['items' => "الوزن القائم للأسطوانة ({$serialNumber}) أقل من وزنها الفارغ."]);
                }
                $netWeight = round($grossWeight - $tareWeight, 4);
            }

            $replacedSerialId = ! empty($serialEntry['replaced_serial_id']) ? (int) $serialEntry['replaced_serial_id'] : null;
            if ($serial && $replacedSerialId === (int) $serial->id) {
                throw ValidationException::withMessages(['items' => 'لا يمكن أن تكون الأسطوانة المستبدلة هي الأسطوانة الواردة نفسها.']);
            }

            $existingRow = $serial ? $existingRows->first(function ($row) use ($serial, $retainedIds): bool {
                return ! in_array((int) $row->id, $retainedIds, true)
                    && $row->serial_id !== null
                    && (int) $row->serial_id === (int) $serial->id;
            }) : null;

            if (! $existingRow) {
                $existingRow = $existingRows->first(function ($row) use ($serialNumber, $retainedIds): bool {
                    if (in_array((int) $row->id, $retainedIds, true) || $row->serial_id !== null) {
                        return false;
                    }
                    $rowNumber = $row->serial_number;
                    return $rowNumber !== null && mb_strtolower(trim((string) $rowNumber)) === mb_strtolower($serialNumber);
                });
            }

            $attributes = [
                'serial_id' => $serial?->id,
                'serial_number' => $serialNumber,
                'replaced_serial_id' => $replacedSerialId,
                'tare_weight' => $tareWeight,
                'gross_weight' => $grossWeight,
                'net_weight' => $netWeight,
                'notes' => $serialEntry['notes'] ?? null,
            ];

            if ($existingRow) {
                if ($existingRow->trashed()) {
                    $existingRow->restore();
                }
                $existingRow->update($attributes);
                $retainedIds[] = (int) $existingRow->id;
            } else {
                $newRow = $receiptItem->serials()->create($attributes);
                $retainedIds[] = (int) $newRow->id;
            }
        }

        $removedRows = $receiptItem->serials();
        if ($retainedIds !== []) {
            $removedRows->whereNotIn('id', $retainedIds);
        }
        $removedRows->delete();
        $receiptItem->unsetRelation('serials');
    }

    protected function applyItemReceiptInventoryEffects(PurchaseReceipt $receipt, PurchaseReceiptItem $item, int $userId): void
    {
        $item->loadMissing(['product', 'productUnit', 'serials.serial']);
        $product = $item->product;
        $productUnit = $item->productUnit;
        if (! $product || ! $productUnit || (int) $productUnit->product_id !== (int) $item->product_id) {
            throw ValidationException::withMessages(['items' => 'الصنف أو وحدة القياس غير صالحين لبند الاستلام.']);
        }

        $trackingType = $product->tracking_type;
        $isWeightSerial = $trackingType === 'by_weight_serial';
        $isStandardSerial = $trackingType === 'by_serial';
        $documentSerials = $item->serials;
        $acceptedQuantity = (float) $item->quantity_accepted;

        if (! $isWeightSerial && ! $isStandardSerial) {
            if ($documentSerials->isNotEmpty()) {
                throw ValidationException::withMessages(['items' => 'أُرفقت سيريالات لصنف لا يستخدم سياسة التتبع بالسيريال.']);
            }
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
                serialId: null,
                notes: "استلام مشتريات بموجب السند رقم: {$receipt->receipt_number}",
                userId: $userId
            );
            return;
        }

        if ($documentSerials->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'لا يمكن تأكيد استلام صنف مسلسل دون بيانات سيريالاته.']);
        }
        if ($isWeightSerial && (! (bool) $productUnit->is_base_unit || (float) $productUnit->conversion_factor !== 1.0)) {
            throw ValidationException::withMessages(['items' => 'استلام الغاز بالسيريال الوزني يجب أن يكون بوحدة الصنف الأساسية بالكيلوجرام، ومعامل تحويل 1.']);
        }
        if ($isStandardSerial && (round($acceptedQuantity, 4) !== (float) (int) $acceptedQuantity || $documentSerials->count() !== (int) $acceptedQuantity)) {
            throw ValidationException::withMessages(['items' => 'عدد السيريالات يجب أن يساوي الكمية المقبولة الصحيحة دون كسور.']);
        }

        $prepared = [];
        $seenIdentifiers = [];
        $replacementIds = [];
        $totalNetWeight = 0.0;

        foreach ($documentSerials as $docSerial) {
            $serialNumber = $docSerial->serial_number ?? $docSerial->serial?->serial_number;
            $serialNumber = $serialNumber !== null ? trim((string) $serialNumber) : null;
            $serial = $this->resolveExistingSerial(
                (int) $item->product_id,
                $docSerial->serial_id !== null ? (int) $docSerial->serial_id : null,
                $serialNumber,
                true
            );
            $serialNumber = $serial ? (string) $serial->serial_number : $serialNumber;
            if ($serialNumber === null || $serialNumber === '') {
                throw ValidationException::withMessages(['items' => 'أحد صفوف الاستلام لا يحتوي على رقم أسطوانة صالح.']);
            }

            $identifier = $serial ? 'id_' . $serial->id : 'num_' . mb_strtolower($serialNumber);
            if (isset($seenIdentifiers[$identifier])) {
                throw ValidationException::withMessages(['items' => "الأسطوانة ({$serialNumber}) مكررة في بند الاستلام."]);
            }
            $seenIdentifiers[$identifier] = true;

            // التحقق المرن: منع استلام الأسطوانات التالفة أو المستبدلة أو التي ما زالت على خط المعالجة فقط
            if ($serial) {
                if (in_array($serial->status, [StockSerialStatus::SCRAPPED, StockSerialStatus::REPLACED], true)) {
                    throw ValidationException::withMessages([
                        'items' => "الأسطوانة ({$serialNumber}) مسجلة بحالة ({$serial->status->label()}) ولا يمكن استلامها.",
                    ]);
                }
                if ($serial->status === StockSerialStatus::IN_SERVICE) {
                    throw ValidationException::withMessages([
                        'items' => "الأسطوانة ({$serialNumber}) قيد التشغيل حالياً بالمحطة؛ يجب إثبات فراغها أولاً قبل استلامها.",
                    ]);
                }
            }

            $tareWeight = $this->parseReceiptWeight($docSerial->tare_weight ?? $serial?->tare_weight, 'الوزن الفارغ');
            $grossWeight = $this->parseReceiptWeight($docSerial->gross_weight, 'الوزن القائم');
            $netWeight = null;
            if ($isWeightSerial) {
                if ($tareWeight === null || $tareWeight <= 0 || $grossWeight === null || $grossWeight <= $tareWeight) {
                    throw ValidationException::withMessages(['items' => "الأسطوانة ({$serialNumber}) تحتاج وزناً فارغاً موجباً ووزناً قائماً أكبر منه."]);
                }
                $netWeight = round($grossWeight - $tareWeight, 4);
                $totalNetWeight = round($totalNetWeight + $netWeight, 4);
            }

            $replacedSerial = null;
            if ($docSerial->replaced_serial_id !== null) {
                $replacedSerial = $this->resolveExistingSerial((int) $item->product_id, (int) $docSerial->replaced_serial_id, null, true);
                if ($serial && (int) $replacedSerial->id === (int) $serial->id) {
                    throw ValidationException::withMessages(['items' => 'الأسطوانة المستبدلة لا يمكن أن تكون الواردة نفسها.']);
                }
                if (! in_array($replacedSerial->status, [StockSerialStatus::WITH_VENDOR, StockSerialStatus::EMPTY], true)) {
                    throw ValidationException::withMessages(['items' => "الأسطوانة المحددة للاستبدال ({$replacedSerial->serial_number}) يجب أن تكون لدى المورد أو فارغة بالمستودع."]);
                }
                if (isset($replacementIds[(int) $replacedSerial->id])) {
                    throw ValidationException::withMessages(['items' => 'لا يجوز استبدال الأسطوانة القديمة نفسها بأكثر من أسطوانة في هذا البند.']);
                }
                $replacementIds[(int) $replacedSerial->id] = true;
            }

            $previousStatus = $serial ? $serial->status->value : null;

            $prepared[] = compact(
                'docSerial',
                'serial',
                'serialNumber',
                'previousStatus',
                'tareWeight',
                'grossWeight',
                'netWeight',
                'replacedSerial'
            );
        }

        foreach ($prepared as $entry) {
            if ($entry['serial'] && isset($replacementIds[(int) $entry['serial']->id])) {
                throw ValidationException::withMessages(['items' => 'لا يجوز تسجيل الأسطوانة نفسها كواردة وكأسطوانة مستبدلة في البند نفسه.']);
            }
        }

        if ($isWeightSerial && abs($totalNetWeight - $acceptedQuantity) > 1.0) {
            throw ValidationException::withMessages([
                'items' => "الكمية المقبولة ({$acceptedQuantity} كجم) لا تطابق مجموع صافي أوزان الأسطوانات ({$totalNetWeight} كجم) بهامش سماحية معقول.",
            ]);
        }

        foreach ($prepared as $entry) {
            $serial = $entry['serial'];
            if (! $serial) {
                $serial = StockSerial::create([
                    'product_id' => $item->product_id,
                    'serial_number' => $entry['serialNumber'],
                    'tare_weight' => $entry['tareWeight'],
                    'gross_weight' => $entry['grossWeight'],
                    'current_gas_weight' => $entry['netWeight'],
                    'warehouse_id' => $receipt->warehouse_id,
                    'location_id' => $item->location_id,
                    'batch_id' => $item->batch_id,
                    'status' => StockSerialStatus::IN_STOCK,
                    'notes' => "إنشاء سيريال عند تأكيد سند الاستلام رقم: {$receipt->receipt_number}",
                ]);
            } else {
                if ($isWeightSerial) {
                    $serial->update(['tare_weight' => $entry['tareWeight']]);
                }
                $serial->update(['batch_id' => $item->batch_id]);
                $serial->markAsInStock(
                    warehouseId: (int) $receipt->warehouse_id,
                    locationId: $item->location_id ? (int) $item->location_id : null,
                    grossWeight: $isWeightSerial ? $entry['grossWeight'] : null,
                    gasWeight: $isWeightSerial ? $entry['netWeight'] : null,
                    notes: "استلام/تحديث سيريال بموجب السند رقم: {$receipt->receipt_number}"
                );
            }

            // الحفاظ على الحالة الأصلية قبل أول استلام لتسهيل الإلغاء بدقة
            $statusToPreserve = $entry['docSerial']->previous_status ?? $entry['previousStatus'];

            $entry['docSerial']->update([
                'serial_id' => $serial->id,
                'serial_number' => $entry['serialNumber'],
                'previous_status' => $statusToPreserve,
                'tare_weight' => $entry['tareWeight'],
                'gross_weight' => $entry['grossWeight'],
                'net_weight' => $isWeightSerial ? $entry['netWeight'] : $entry['docSerial']->net_weight,
            ]);
            $entry['docSerial']->unsetRelation('serial');

            if ($entry['replacedSerial']) {
                $entry['replacedSerial']->markAsReplaced(
                    "استبدال موثق بالأسطوانة رقم {$serial->serial_number} بموجب السند رقم: {$receipt->receipt_number}"
                );
            }

            $movementQuantity = $isWeightSerial ? $entry['netWeight'] : 1.0000;
            $this->stockMovementService->recordMovement(
                productId: (int) $item->product_id,
                warehouseId: (int) $receipt->warehouse_id,
                productUnitId: (int) $item->product_unit_id,
                movementType: 'purchase',
                quantity: $movementQuantity,
                unitCost: (float) $item->unit_cost,
                reference: $receipt,
                locationId: $item->location_id ? (int) $item->location_id : null,
                batchId: $item->batch_id ? (int) $item->batch_id : null,
                serialId: (int) $serial->id,
                notes: $isWeightSerial
                    ? "استلام أسطوانة رقم ({$serial->serial_number}) صافي غاز ({$movementQuantity} كجم) بموجب السند: {$receipt->receipt_number}"
                    : "استلام سيريال رقم ({$serial->serial_number}) بموجب السند: {$receipt->receipt_number}",
                userId: $userId
            );
        }
    }

    protected function assertSerialReceiptCanBeReversed(PurchaseReceipt $receipt): void
    {
        $receipt->loadMissing(['items.serials']);

        foreach ($receipt->items as $item) {
            foreach ($item->serials as $docSerial) {
                if (! $docSerial->serial_id) {
                    continue;
                }

                $serial = StockSerial::withTrashed()->find($docSerial->serial_id);
                if (! $serial) {
                    continue;
                }

                if ($serial->trashed()) {
                    $serial->restore();
                }

                if ($serial->status !== StockSerialStatus::IN_STOCK) {
                    throw ValidationException::withMessages([
                        'status' => "تعذر إلغاء أو تعديل السند لأن الأسطوانة ({$serial->serial_number}) تغيرت حالتها إلى ({$serial->status->label()}) بعد الاستلام. يرجى إعادتها إلى المخزن أولاً أو إجراء تسوية مخزنية.",
                    ]);
                }

                if ((int) $serial->warehouse_id !== (int) $receipt->warehouse_id) {
                    throw ValidationException::withMessages([
                        'status' => "تعذر إلغاء أو تعديل السند لأن الأسطوانة ({$serial->serial_number}) نُقلت إلى مستودع آخر.",
                    ]);
                }
            }
        }
    }

    /**
     * معالجة استبعاد أسطوانة تم حذف سطرها أثناء تعديل السند المستلم
     */
    protected function revertRemovedSerial(int $serialId, array $oldMeta, int $warehouseId, string $receiptNumber): void
    {
        $serial = StockSerial::find($serialId);
        if (! $serial) {
            return;
        }

        $note = "استبعاد الأسطوانة إثر تعديل السند رقم: {$receiptNumber}";

        if ($oldMeta['previous_status'] !== null) {
            $targetStatus = StockSerialStatus::tryFrom($oldMeta['previous_status']) ?? StockSerialStatus::WITH_VENDOR;
            if ($targetStatus === StockSerialStatus::WITH_VENDOR) {
                $serial->markAsWithVendor($note);
            } elseif ($targetStatus === StockSerialStatus::EMPTY) {
                $serial->markAsEmpty($warehouseId, null, $note);
            } else {
                $serial->update([
                    'status' => $targetStatus,
                    'current_gas_weight' => 0.0000,
                    'gross_weight' => $serial->tare_weight,
                    'notes' => trim(($serial->notes ? $serial->notes . PHP_EOL : '') . $note),
                ]);
            }
        } else {
            $serial->delete();
        }

        if (! empty($oldMeta['replaced_serial_id'])) {
            $replaced = StockSerial::find((int) $oldMeta['replaced_serial_id']);
            if ($replaced && $replaced->status === StockSerialStatus::REPLACED) {
                $replaced->markAsWithVendor("إلغاء استبدال إثر تعديل السند رقم: {$receiptNumber}");
            }
        }
    }

    protected function reverseReceiptSerials(PurchaseReceipt $receipt, int $userId): void
    {
        $receipt->loadMissing(['items.serials.serial', 'items.serials.replacedSerial']);

        foreach ($receipt->items as $item) {
            foreach ($item->serials as $docSerial) {
                $serial = $docSerial->serial;
                if ($serial) {
                    if ($docSerial->previous_status !== null) {
                        $targetStatus = StockSerialStatus::tryFrom($docSerial->previous_status) ?? StockSerialStatus::WITH_VENDOR;
                        $note = "عكس استلام إثر إلغاء السند رقم: {$receipt->receipt_number}";

                        if ($targetStatus === StockSerialStatus::WITH_VENDOR) {
                            $serial->markAsWithVendor($note);
                        } elseif ($targetStatus === StockSerialStatus::EMPTY) {
                            $serial->markAsEmpty((int) $receipt->warehouse_id, null, $note);
                        } else {
                            $serial->update([
                                'status' => $targetStatus,
                                'current_gas_weight' => 0.0000,
                                'gross_weight' => $serial->tare_weight,
                                'notes' => trim(($serial->notes ? $serial->notes . PHP_EOL : '') . $note),
                            ]);
                        }
                    } else {
                        $serial->delete();
                    }
                }

                if ($docSerial->replaced_serial_id && $docSerial->replacedSerial) {
                    $replaced = $docSerial->replacedSerial;
                    if ($replaced->status === StockSerialStatus::REPLACED) {
                        $replaced->markAsWithVendor("إلغاء استبدال إثر إلغاء السند رقم: {$receipt->receipt_number}");
                    }
                }
            }
        }
    }

    protected function resolveExistingSerial(int $productId, ?int $serialId, ?string $serialNumber, bool $lock = false): ?StockSerial
    {
        $query = StockSerial::withTrashed();
        if ($lock) {
            $query->lockForUpdate();
        }
        if ($serialId !== null) {
            $serial = $query->find($serialId);
            if (! $serial || (int) $serial->product_id !== $productId) {
                throw ValidationException::withMessages(['items' => 'معرف الأسطوانة غير موجود أو يتبع صنفاً مختلفاً.']);
            }
            if ($serialNumber !== null && $serialNumber !== '' && mb_strtolower(trim((string) $serial->serial_number)) !== mb_strtolower($serialNumber)) {
                throw ValidationException::withMessages(['items' => 'رقم الأسطوانة لا يطابق معرفها. أعد اختيار الأسطوانة أو صحح الرقم.']);
            }
        } elseif ($serialNumber !== null && $serialNumber !== '') {
            $serial = $query->where('product_id', $productId)->where('serial_number', $serialNumber)->first();
        } else {
            return null;
        }

        if ($serial && $serial->trashed()) {
            $serial->restore();
        }

        return $serial;
    }

    protected function parseReceiptWeight(mixed $value, string $label): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            throw ValidationException::withMessages(['items' => "{$label} يجب أن يكون قيمة رقمية."]);
        }
        $weight = (float) $value;
        if (! is_finite($weight) || $weight < 0 || $weight > 9999999999.9999) {
            throw ValidationException::withMessages(['items' => "{$label} خارج النطاق المسموح."]);
        }
        return round($weight, 4);
    }

    protected function lockPurchaseOrders(array $orderIds): void
    {
        $ids = array_values(array_unique(array_map('intval', array_filter($orderIds))));
        sort($ids, SORT_NUMERIC);
        if ($ids !== []) {
            PurchaseOrder::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
        }
    }

    protected function hasActiveBills(PurchaseReceipt $receipt): bool
    {
        return PurchaseBill::where('receipt_id', $receipt->id)
            ->where('status', '!=', BillStatus::CANCELLED)
            ->exists();
    }

    protected function generateReceiptNumber(): string
    {
        $prefix = 'RC-' . Carbon::now()->format('Ymd');
        $lastRecord = PurchaseReceipt::withTrashed()
            ->where('receipt_number', 'like', "{$prefix}-%")
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();
        $nextSequence = $lastRecord && preg_match('/-(\d{4})$/', $lastRecord->receipt_number, $matches)
            ? (int) $matches[1] + 1
            : 1;
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
            $orderItem->update([
                'received_quantity' => max(0.0, (float) $orderItem->received_quantity - $quantity),
                'updated_by' => $userId,
            ]);
        }
    }

    protected function syncPurchaseOrderStatus(int $purchaseOrderId, int $userId): void
    {
        $order = PurchaseOrder::query()->lockForUpdate()->find($purchaseOrderId);
        if (! $order || in_array($order->status, [PurchaseOrderStatus::CANCELLED, PurchaseOrderStatus::CLOSED], true)) {
            return;
        }
        $order->setRelation('items', $order->items()->orderBy('id')->lockForUpdate()->get());
        $allFulfilled = $order->items->every(fn ($item): bool => (float) $item->received_quantity >= (float) $item->quantity);
        $anyReceived = $order->items->some(fn ($item): bool => (float) $item->received_quantity > 0);
        if ($allFulfilled) {
            $order->update(['status' => PurchaseOrderStatus::RECEIVED, 'updated_by' => $userId]);
        } elseif ($anyReceived) {
            $order->update(['status' => PurchaseOrderStatus::PARTIALLY_RECEIVED, 'updated_by' => $userId]);
        } else {
            $order->update(['status' => PurchaseOrderStatus::CONFIRMED, 'updated_by' => $userId]);
        }
    }
}