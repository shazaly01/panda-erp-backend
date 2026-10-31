<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Services;

use App\Modules\Inventory\Enums\StockSerialStatus;
use App\Modules\Inventory\Models\InventoryDocumentSerial;
use App\Modules\Inventory\Models\StockSerial;
use App\Modules\Inventory\Services\StockMovementService;
use App\Modules\Purchasing\Enums\IssueStatus;
use App\Modules\Purchasing\Enums\RequisitionStatus;
use App\Modules\Purchasing\Models\PurchaseIssue;
use App\Modules\Purchasing\Models\PurchaseIssueItem;
use App\Modules\Purchasing\Models\PurchaseRequisition;
use App\Modules\Purchasing\Models\PurchaseRequisitionItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PurchaseIssueService
{
    public function __construct(
        protected readonly StockMovementService $stockMovementService
    ) {}

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = PurchaseIssue::query()
            ->with([
                'warehouse',
                'department',
                'recipient',
                'issuer',
                'creator',
            ])
            ->withCount('items');

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('issue_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (! empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        if (! empty($filters['recipient_id'])) {
            $query->where('recipient_id', $filters['recipient_id']);
        }

        if (! empty($filters['requisition_id'])) {
            $query->where('requisition_id', $filters['requisition_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('issue_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('issue_date', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    public function find(int $id): PurchaseIssue
    {
        return PurchaseIssue::with([
            'warehouse',
            'requisition',
            'department',
            'recipient',
            'issuer',
            'creator',
            'updater',
            'items.product',
            'items.productUnit.unit',
            'items.location',
            'items.batch',
            'items.serials.serial',
        ])->findOrFail($id);
    }

    public function create(array $data, int $userId): PurchaseIssue
    {
        return DB::transaction(function () use ($data, $userId): PurchaseIssue {
            $issueNumber = $this->generateIssueNumber();

            $issue = PurchaseIssue::create([
                'issue_number'   => $issueNumber,
                'requisition_id' => $data['requisition_id'] ?? null,
                'warehouse_id'   => $data['warehouse_id'],
                'department_id'  => $data['department_id'] ?? null,
                'recipient_id'   => $data['recipient_id'] ?? null,
                'issue_date'     => $data['issue_date'] ?? Carbon::now()->toDateString(),
                'status'         => IssueStatus::DRAFT,
                'total_cost'     => 0.0000,
                'notes'          => $data['notes'] ?? null,
                'created_by'     => $userId,
                'updated_by'     => $userId,
            ]);

            $totalIssueCost = 0.0000;

            foreach ($data['items'] as $itemData) {
                $quantity = (float) $itemData['quantity'];
                $unitCost = isset($itemData['unit_cost']) ? (float) $itemData['unit_cost'] : 0.0000;
                $lineTotalCost = round($quantity * $unitCost, 4);
                $totalIssueCost += $lineTotalCost;

                /** @var PurchaseIssueItem $issueItem */
                $issueItem = $issue->items()->create([
                    'requisition_item_id' => $itemData['requisition_item_id'] ?? null,
                    'product_id'          => $itemData['product_id'],
                    'product_unit_id'     => $itemData['product_unit_id'],
                    'location_id'         => $itemData['location_id'] ?? null,
                    'batch_id'            => $itemData['batch_id'] ?? null,
                    'quantity'            => $quantity,
                    'unit_cost'           => $unitCost,
                    'total_cost'          => $lineTotalCost,
                    'notes'               => $itemData['notes'] ?? null,
                    'created_by'          => $userId,
                    'updated_by'          => $userId,
                ]);

                if (! empty($itemData['serials']) && is_array($itemData['serials'])) {
                    $this->syncItemSerials($issueItem, $itemData['serials']);
                }
            }

            $issue->update(['total_cost' => $totalIssueCost]);

            return $this->find($issue->id);
        });
    }

    public function update(PurchaseIssue $issue, array $data, int $userId): PurchaseIssue
    {
        if (! in_array($issue->status, [IssueStatus::DRAFT, IssueStatus::ISSUED], true)) {
            throw new RuntimeException('لا يمكن تعديل إذن الصرف إلا إذا كان في حالة مسودة أو منصرف.');
        }

        return DB::transaction(function () use ($issue, $data, $userId): PurchaseIssue {
            $isIssued = ($issue->status === IssueStatus::ISSUED);
            $oldRequisitionId = $issue->requisition_id;

            // 1. في حال كان الإذن منصرفاً مسبقاً، نقوم بعكس حركات خروج المخزون وإعادة الأسطوانات لحالتها السابقة
            if ($isIssued) {
                $issue->loadMissing('items.serials.serial');

                foreach ($issue->items as $oldItem) {
                    if (! empty($oldItem->requisition_item_id)) {
                        $this->decrementRequisitionIssuedQuantity(
                            (int) $oldItem->requisition_item_id,
                            (float) $oldItem->quantity,
                            $userId
                        );
                    }

                    foreach ($oldItem->serials as $docSerial) {
                        /** @var StockSerial|null $serial */
                        $serial = $docSerial->serial;
                        if ($serial) {
                            $currentGas = (float) ($serial->current_gas_weight ?? 0.0);
                            if ($currentGas > 0.0 || $serial->status === StockSerialStatus::IN_SERVICE) {
                                $serial->markAsInStock(
                                    warehouseId: (int) $issue->warehouse_id,
                                    locationId: $oldItem->location_id ? (int) $oldItem->location_id : null,
                                    notes: "إعادة الأسطوانة ممتلئة للمستودع لعكس أثر إذن الصرف رقم: {$issue->issue_number} للتعديل"
                                );
                            } else {
                                $serial->markAsEmpty(
                                    warehouseId: (int) $issue->warehouse_id,
                                    locationId: $oldItem->location_id ? (int) $oldItem->location_id : null,
                                    notes: "إعادة الأسطوانة الفارغة للمستودع لعكس أثر إذن الصرف رقم: {$issue->issue_number} للتعديل"
                                );
                            }
                        }
                    }
                }

                $this->stockMovementService->clearDocumentMovements($issue);
            }

            // 2. تحديث رأس إذن الصرف
            $issue->update([
                'requisition_id' => array_key_exists('requisition_id', $data) ? $data['requisition_id'] : $issue->requisition_id,
                'warehouse_id'   => $data['warehouse_id'] ?? $issue->warehouse_id,
                'department_id'  => array_key_exists('department_id', $data) ? $data['department_id'] : $issue->department_id,
                'recipient_id'   => array_key_exists('recipient_id', $data) ? $data['recipient_id'] : $issue->recipient_id,
                'issue_date'     => $data['issue_date'] ?? $issue->issue_date,
                'notes'          => array_key_exists('notes', $data) ? $data['notes'] : $issue->notes,
                'updated_by'     => $userId,
            ]);

            // 3. مزامنة البنود وتحديث تكلفة الإذن الإجمالية
            if (isset($data['items']) && is_array($data['items'])) {
                $existingItems = $issue->items()->get()->keyBy('id');
                $submittedItemIds = collect($data['items'])
                    ->pluck('id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->toArray();

                foreach ($existingItems as $existingId => $existingItem) {
                    if (! in_array($existingId, $submittedItemIds, true)) {
                        $existingItem->serials()->delete();
                        $existingItem->delete();
                    }
                }

                $totalIssueCost = 0.0000;

                foreach ($data['items'] as $itemData) {
                    $quantity = (float) $itemData['quantity'];
                    $unitCost = isset($itemData['unit_cost']) ? (float) $itemData['unit_cost'] : 0.0000;
                    $lineTotalCost = round($quantity * $unitCost, 4);
                    $totalIssueCost += $lineTotalCost;

                    if (! empty($itemData['id']) && $existingItems->has($itemData['id'])) {
                        /** @var PurchaseIssueItem $existingItem */
                        $existingItem = $existingItems->get($itemData['id']);
                        $existingItem->update([
                            'requisition_item_id' => $itemData['requisition_item_id'] ?? $existingItem->requisition_item_id,
                            'product_id'          => $itemData['product_id'],
                            'product_unit_id'     => $itemData['product_unit_id'],
                            'location_id'         => $itemData['location_id'] ?? null,
                            'batch_id'            => $itemData['batch_id'] ?? null,
                            'quantity'            => $quantity,
                            'unit_cost'           => $unitCost,
                            'total_cost'          => $lineTotalCost,
                            'notes'               => $itemData['notes'] ?? null,
                            'updated_by'          => $userId,
                        ]);

                        $this->syncItemSerials($existingItem, $itemData['serials'] ?? []);
                    } else {
                        /** @var PurchaseIssueItem $newItem */
                        $newItem = $issue->items()->create([
                            'requisition_item_id' => $itemData['requisition_item_id'] ?? null,
                            'product_id'          => $itemData['product_id'],
                            'product_unit_id'     => $itemData['product_unit_id'],
                            'location_id'         => $itemData['location_id'] ?? null,
                            'batch_id'            => $itemData['batch_id'] ?? null,
                            'quantity'            => $quantity,
                            'unit_cost'           => $unitCost,
                            'total_cost'          => $lineTotalCost,
                            'notes'               => $itemData['notes'] ?? null,
                            'created_by'          => $userId,
                            'updated_by'          => $userId,
                        ]);

                        $this->syncItemSerials($newItem, $itemData['serials'] ?? []);
                    }
                }

                $issue->update(['total_cost' => $totalIssueCost]);
            }

            // 4. إذا كان الإذن في حالة منصرف: إعادة تسجيل حركة المخزون وتحديث طلبات الاحتياج والأرقام التسلسلية
            if ($isIssued) {
                $issue->loadMissing(['items.productUnit', 'items.serials.serial']);

                foreach ($issue->items as $item) {
                    $this->applyItemIssueInventoryEffects($issue, $item, $userId);

                    if (! empty($item->requisition_item_id)) {
                        $this->incrementRequisitionIssuedQuantity(
                            (int) $item->requisition_item_id,
                            (float) $item->quantity,
                            $userId
                        );
                    }
                }

                if ($oldRequisitionId && $oldRequisitionId !== $issue->requisition_id) {
                    $this->syncRequisitionStatus((int) $oldRequisitionId, $userId);
                }

                if ($issue->requisition_id) {
                    $this->syncRequisitionStatus((int) $issue->requisition_id, $userId);
                }
            }

            return $this->find($issue->id);
        });
    }

    public function delete(PurchaseIssue $issue): bool
    {
        if ($issue->status !== IssueStatus::DRAFT) {
            throw new RuntimeException('لا يمكن حذف إذن الصرف إلا إذا كان في حالة مسودة.');
        }

        return DB::transaction(function () use ($issue): bool {
            foreach ($issue->items as $item) {
                $item->serials()->delete();
            }
            $issue->items()->delete();
            return (bool) $issue->delete();
        });
    }

    public function confirmIssue(PurchaseIssue $issue, int $userId): PurchaseIssue
    {
        if ($issue->status !== IssueStatus::DRAFT) {
            throw new RuntimeException('لا يمكن تأكيد الصرف إلا للأذونات في حالة مسودة.');
        }

        $issue->loadMissing(['items.productUnit', 'items.serials.serial']);

        if ($issue->items->isEmpty()) {
            throw new RuntimeException('لا يمكن تأكيد إذن صرف لا يحتوي على أي بنود.');
        }

        return DB::transaction(function () use ($issue, $userId): PurchaseIssue {
            foreach ($issue->items as $item) {
                $this->applyItemIssueInventoryEffects($issue, $item, $userId);

                if (! empty($item->requisition_item_id)) {
                    $this->incrementRequisitionIssuedQuantity(
                        (int) $item->requisition_item_id,
                        (float) $item->quantity,
                        $userId
                    );
                }
            }

            $issue->update([
                'status'     => IssueStatus::ISSUED,
                'issued_by'  => $userId,
                'issued_at'  => Carbon::now(),
                'updated_by' => $userId,
            ]);

            if ($issue->requisition_id) {
                $this->syncRequisitionStatus((int) $issue->requisition_id, $userId);
            }

            return $this->find($issue->id);
        });
    }

    public function cancelIssue(PurchaseIssue $issue, int $userId): PurchaseIssue
    {
        if ($issue->status === IssueStatus::CANCELLED) {
            throw new RuntimeException('إذن الصرف ملغي مسبقاً.');
        }

        return DB::transaction(function () use ($issue, $userId): PurchaseIssue {
            $issue->loadMissing('items.serials.serial');

            if ($issue->status === IssueStatus::ISSUED) {
                $this->stockMovementService->clearDocumentMovements($issue);

                foreach ($issue->items as $item) {
                    foreach ($item->serials as $docSerial) {
                        /** @var StockSerial|null $serial */
                        $serial = $docSerial->serial;
                        if ($serial) {
                            $currentGas = (float) ($serial->current_gas_weight ?? 0.0);
                            if ($currentGas > 0.0 || $serial->status === StockSerialStatus::IN_SERVICE) {
                                $serial->markAsInStock(
                                    warehouseId: (int) $issue->warehouse_id,
                                    locationId: $item->location_id ? (int) $item->location_id : null,
                                    notes: "إلغاء إذن الصرف رقم: {$issue->issue_number} وإعادة الأسطوانة ممتلئة للمستودع"
                                );
                            } else {
                                $serial->markAsEmpty(
                                    warehouseId: (int) $issue->warehouse_id,
                                    locationId: $item->location_id ? (int) $item->location_id : null,
                                    notes: "إلغاء إذن الصرف رقم: {$issue->issue_number} وإعادة الأسطوانة الفارغة للمستودع"
                                );
                            }
                        }
                    }

                    if (! empty($item->requisition_item_id)) {
                        $this->decrementRequisitionIssuedQuantity(
                            (int) $item->requisition_item_id,
                            (float) $item->quantity,
                            $userId
                        );
                    }
                }

                if ($issue->requisition_id) {
                    $this->syncRequisitionStatus((int) $issue->requisition_id, $userId);
                }
            }

            $issue->update([
                'status'     => IssueStatus::CANCELLED,
                'updated_by' => $userId,
            ]);

            return $this->find($issue->id);
        });
    }

    public function createDraftFromRequisition(
        PurchaseRequisition $requisition,
        int $warehouseId,
        array $items,
        int $userId
    ): PurchaseIssue {
        return $this->create([
            'requisition_id' => $requisition->id,
            'warehouse_id'   => $warehouseId,
            'department_id'  => $requisition->department_id,
            'recipient_id'   => $requisition->requested_by,
            'issue_date'     => Carbon::now()->toDateString(),
            'notes'          => "إذن صرف ناتج آلياً عن فرز طلب الاحتياج رقم: {$requisition->requisition_number}",
            'items'          => $items,
        ], $userId);
    }

    /**
     * مزامنة وتوثيق الأرقام التسلسلية المحددة لبند الصرف مع احتساب صافي الغاز
     */
    protected function syncItemSerials(PurchaseIssueItem $issueItem, array $serialsData): void
    {
        $issueItem->serials()->delete();

        foreach ($serialsData as $serialEntry) {
            $serialId = $serialEntry['serial_id'] ?? null;
            if (! $serialId) {
                continue;
            }

            $serial = StockSerial::find($serialId);
            if (! $serial) {
                continue;
            }

            $grossWeight = isset($serialEntry['gross_weight']) && $serialEntry['gross_weight'] !== ''
                ? (float) $serialEntry['gross_weight']
                : ($serial->gross_weight !== null ? (float) $serial->gross_weight : null);

            $netWeight = isset($serialEntry['net_weight']) && $serialEntry['net_weight'] !== ''
                ? (float) $serialEntry['net_weight']
                : null;

            if ($netWeight === null && $grossWeight !== null && $serial->tare_weight !== null) {
                $netWeight = $serial->calculateNetWeight($grossWeight);
            } elseif ($netWeight === null) {
                $netWeight = (float) ($serial->current_gas_weight ?? 0.0);
            }

            $issueItem->serials()->create([
                'serial_id'          => (int) $serialId,
                'replaced_serial_id' => null,
                'gross_weight'       => $grossWeight,
                'net_weight'         => $netWeight,
                'notes'              => $serialEntry['notes'] ?? null,
            ]);
        }
    }

    /**
     * تطبيق الأثر المخزني للبند والتمييز بين الصرف للتشغيل بالمحطة وإرسال الأسطوانات الفارغة للتعبئة
     */
    protected function applyItemIssueInventoryEffects(
        PurchaseIssue $issue,
        PurchaseIssueItem $item,
        int $userId
    ): void {
        $documentSerials = $item->serials;

        if ($documentSerials->isNotEmpty()) {
            foreach ($documentSerials as $docSerial) {
                /** @var StockSerial|null $serial */
                $serial = $docSerial->serial;
                if (! $serial) {
                    continue;
                }

                $grossWeight = $docSerial->gross_weight !== null
                    ? (float) $docSerial->gross_weight
                    : (float) ($serial->gross_weight ?? 0.0);

                $netGasWeight = $docSerial->net_weight !== null
                    ? (float) $docSerial->net_weight
                    : (float) ($serial->current_gas_weight ?? 0.0);

                // التحقق من طبيعة خروج الأسطوانة (فارغة للمورد للتعبئة أم ممتلئة للتشغيل بالمحطة)
                $isCylinderEmpty = ($serial->status === StockSerialStatus::EMPTY)
                    || ($netGasWeight <= 0.0 && (float) ($serial->current_gas_weight ?? 0.0) <= 0.0);

                if ($isCylinderEmpty) {
                    // المسار الأول: خروج أسطوانة فارغة للتعبئة لدى المورد
                    $serial->markAsWithVendor("صرف أسطوانة فارغة للتعبئة لدى المورد بموجب إذن الصرف: {$issue->issue_number}");
                    // لا يتم تسجيل خصم مخزني لكمية الغاز لأن رصيد الأسطوانة صفر فعلياً
                } else {
                    // المسار الثاني: أسطوانة ممتلئة تخرج للتشغيل وضخ الكلور بالمحطة
                    $effectiveGasQuantity = $netGasWeight > 0.0
                        ? $netGasWeight
                        : (float) ($serial->current_gas_weight ?? 0.0);

                    // تحويل حالة الأسطوانة إلى قيد التشغيل بالمحطة
                    $serial->markAsInService("صرف أسطوانة للتشغيل وضخ الكلور بمحطة المياه بموجب إذن الصرف: {$issue->issue_number}");

                    // خصم صافي وزن الغاز الفعلي المقاس بالكيلوجرام فقط دون أي أوزان وهمية
                    if ($effectiveGasQuantity > 0.0) {
                        $this->stockMovementService->recordMovement(
                            productId: (int) $item->product_id,
                            warehouseId: (int) $issue->warehouse_id,
                            productUnitId: (int) $item->product_unit_id,
                            movementType: 'out',
                            quantity: $effectiveGasQuantity,
                            unitCost: (float) $item->unit_cost,
                            reference: $issue,
                            locationId: $item->location_id ? (int) $item->location_id : null,
                            batchId: $item->batch_id ? (int) $item->batch_id : null,
                            serialId: $serial->id,
                            notes: "صرف غاز أسطوانة رقم ({$serial->serial_number}) كمية ({$effectiveGasQuantity} كجم) للتشغيل بموجب إذن الصرف: {$issue->issue_number}",
                            userId: $userId
                        );
                    }
                }
            }
        } else {
            // صنف عادي لا يتبع بالأرقام التسلسلية
            $this->stockMovementService->recordMovement(
                productId: (int) $item->product_id,
                warehouseId: (int) $issue->warehouse_id,
                productUnitId: (int) $item->product_unit_id,
                movementType: 'out',
                quantity: (float) $item->quantity,
                unitCost: (float) $item->unit_cost,
                reference: $issue,
                locationId: $item->location_id ? (int) $item->location_id : null,
                batchId: $item->batch_id ? (int) $item->batch_id : null,
                serialId: null,
                notes: $item->notes ?? "صرف مخزني بموجب إذن الصرف: {$issue->issue_number}",
                userId: $userId
            );
        }
    }

    protected function generateIssueNumber(): string
    {
        $prefix = 'ISS-' . Carbon::now()->format('Ymd');

        $lastRecord = PurchaseIssue::withTrashed()
            ->where('issue_number', 'like', "{$prefix}-%")
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if ($lastRecord && preg_match('/-(\d{4})$/', $lastRecord->issue_number, $matches)) {
            $nextSequence = (int) $matches[1] + 1;
        } else {
            $nextSequence = 1;
        }

        return sprintf('%s-%04d', $prefix, $nextSequence);
    }

    protected function incrementRequisitionIssuedQuantity(int $requisitionItemId, float $quantity, int $userId): void
    {
        $reqItem = PurchaseRequisitionItem::lockForUpdate()->find($requisitionItemId);
        if ($reqItem) {
            $reqItem->increment('quantity_issued', $quantity, ['updated_by' => $userId]);
        }
    }

    protected function decrementRequisitionIssuedQuantity(int $requisitionItemId, float $quantity, int $userId): void
    {
        $reqItem = PurchaseRequisitionItem::lockForUpdate()->find($requisitionItemId);
        if ($reqItem) {
            $newQuantity = max(0.0, (float) $reqItem->quantity_issued - $quantity);
            $reqItem->update([
                'quantity_issued' => $newQuantity,
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