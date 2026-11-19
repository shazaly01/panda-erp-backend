<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Services;

use App\Models\User;
use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Models\ProductUnit;
use App\Modules\Inventory\Models\Transfer;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\TransferService;
use App\Modules\Purchasing\Enums\RequisitionStatus;
use App\Modules\Purchasing\Models\PurchaseIssue;
use App\Modules\Purchasing\Models\PurchaseRequisition;
use App\Modules\Purchasing\Models\PurchaseRequisitionItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

class PurchaseRequisitionService
{
    public function __construct(
        protected PurchaseIssueService $issueService,
        protected TransferService $transferService
    ) {}

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = PurchaseRequisition::query()
            ->with([
                'department',
                'requester',
                'receiver',
                'approver',
            ])
            ->withCount('items');

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('requisition_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        if (! empty($filters['requested_by'])) {
            $query->where('requested_by', $filters['requested_by']);
        }

        if (! empty($filters['received_by'])) {
            $query->where('received_by', $filters['received_by']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('request_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('request_date', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    public function find(int $id): PurchaseRequisition
    {
        return PurchaseRequisition::with([
            'department',
            'requester',
            'receiver',
            'approver',
            'creator',
            'updater',
            'items.product',
            'items.productUnit.unit',
            'items.creator',
            'items.updater',
        ])->findOrFail($id);
    }

    public function create(array $data, int $userId): PurchaseRequisition
    {
        return DB::transaction(function () use ($data, $userId): PurchaseRequisition {
            $requisitionNumber = $this->generateRequisitionNumber();

            $user = User::find($userId);
            $canChangeDepartment = $user?->can('changeDepartment', PurchaseRequisition::class) ?? false;

            $departmentId = $canChangeDepartment
                ? ($data['department_id'] ?? $user?->department_id)
                : ($user?->department_id ?? $data['department_id'] ?? null);

            $requisition = PurchaseRequisition::create([
                'requisition_number' => $requisitionNumber,
                'department_id'      => $departmentId,
                'requested_by'       => $userId,
                'received_by'        => $data['received_by'] ?? null,
                'request_date'       => $data['request_date'] ?? Carbon::now()->toDateString(),
                'required_date'      => $data['required_date'] ?? null,
                'priority'           => $data['priority'],
                'status'             => RequisitionStatus::DRAFT,
                'notes'              => $data['notes'] ?? null,
                'created_by'         => $userId,
                'updated_by'         => $userId,
            ]);

            foreach ($data['items'] as $itemData) {
                $requisition->items()->create([
                    'product_id'           => $itemData['product_id'] ?? null,
                    'item_name'            => $itemData['item_name'] ?? null,
                    'product_unit_id'      => $itemData['product_unit_id'] ?? null,
                    'unit_name'            => $itemData['unit_name'] ?? null,
                    'quantity_requested'   => $itemData['quantity_requested'],
                    'quantity_approved'    => 0.0000,
                    'quantity_ordered'     => 0.0000,
                    'quantity_issued'      => 0.0000,
                    'quantity_transferred' => 0.0000,
                    'estimated_unit_cost'  => $itemData['estimated_unit_cost'] ?? 0.0000,
                    'specifications'       => $itemData['specifications'] ?? null,
                    'notes'                => $itemData['notes'] ?? null,
                    'created_by'           => $userId,
                    'updated_by'           => $userId,
                ]);
            }

            return $this->find($requisition->id);
        });
    }

    public function update(PurchaseRequisition $requisition, array $data, int $userId): PurchaseRequisition
    {
        if (! in_array($requisition->status, [RequisitionStatus::DRAFT, RequisitionStatus::PENDING_APPROVAL], true)) {
            throw new RuntimeException('لا يمكن تعديل طلب الشراء إلا إذا كان مسودة أو بانتظار الاعتماد.');
        }

        return DB::transaction(function () use ($requisition, $data, $userId): PurchaseRequisition {
            $user = User::find($userId);
            $canChangeDepartment = $user?->can('changeDepartment', PurchaseRequisition::class) ?? false;

            $departmentId = $requisition->department_id;
            if ($canChangeDepartment && array_key_exists('department_id', $data)) {
                $departmentId = $data['department_id'] ?? null;
            }

            $requisition->update([
                'department_id' => $departmentId,
                'received_by'   => array_key_exists('received_by', $data) ? $data['received_by'] : $requisition->received_by,
                'request_date'  => $data['request_date'] ?? $requisition->request_date,
                'required_date' => array_key_exists('required_date', $data) ? $data['required_date'] : $requisition->required_date,
                'priority'      => $data['priority'] ?? $requisition->priority,
                'notes'         => array_key_exists('notes', $data) ? $data['notes'] : $requisition->notes,
                'updated_by'    => $userId,
            ]);

            if (isset($data['items']) && is_array($data['items'])) {
                $submittedItemIds = collect($data['items'])
                    ->pluck('id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->toArray();

                $requisition->items()
                    ->whereNotIn('id', $submittedItemIds)
                    ->delete();

                foreach ($data['items'] as $itemData) {
                    if (! empty($itemData['id'])) {
                        /** @var PurchaseRequisitionItem|null $existingItem */
                        $existingItem = $requisition->items()->find($itemData['id']);
                        if ($existingItem) {
                            $existingItem->update([
                                'product_id'          => $itemData['product_id'] ?? null,
                                'item_name'           => $itemData['item_name'] ?? null,
                                'product_unit_id'     => $itemData['product_unit_id'] ?? null,
                                'unit_name'           => $itemData['unit_name'] ?? null,
                                'quantity_requested'  => $itemData['quantity_requested'],
                                'estimated_unit_cost' => $itemData['estimated_unit_cost'] ?? $existingItem->estimated_unit_cost,
                                'specifications'      => $itemData['specifications'] ?? null,
                                'notes'               => $itemData['notes'] ?? null,
                                'updated_by'          => $userId,
                            ]);
                        }
                    } else {
                        $requisition->items()->create([
                            'product_id'           => $itemData['product_id'] ?? null,
                            'item_name'            => $itemData['item_name'] ?? null,
                            'product_unit_id'      => $itemData['product_unit_id'] ?? null,
                            'unit_name'            => $itemData['unit_name'] ?? null,
                            'quantity_requested'   => $itemData['quantity_requested'],
                            'quantity_approved'    => 0.0000,
                            'quantity_ordered'     => 0.0000,
                            'quantity_issued'      => 0.0000,
                            'quantity_transferred' => 0.0000,
                            'estimated_unit_cost'  => $itemData['estimated_unit_cost'] ?? 0.0000,
                            'specifications'       => $itemData['specifications'] ?? null,
                            'notes'                => $itemData['notes'] ?? null,
                            'created_by'           => $userId,
                            'updated_by'           => $userId,
                        ]);
                    }
                }
            }

            return $this->find($requisition->id);
        });
    }

    public function delete(PurchaseRequisition $requisition): bool
    {
        if ($requisition->status !== RequisitionStatus::DRAFT) {
            throw new RuntimeException('لا يمكن حذف طلب الشراء إلا إذا كان في حالة مسودة.');
        }

        return DB::transaction(function () use ($requisition): bool {
            $requisition->items()->delete();
            return (bool) $requisition->delete();
        });
    }

    public function submitForApproval(PurchaseRequisition $requisition, int $userId): PurchaseRequisition
    {
        if ($requisition->status !== RequisitionStatus::DRAFT) {
            throw new RuntimeException('يمكن تقديم الطلب للاعتماد فقط إذا كان مسودة.');
        }

        if ($requisition->items()->count() === 0) {
            throw new RuntimeException('لا يمكن تقديم طلب شراء لا يحتوي على بنود.');
        }

        $requisition->update([
            'status'     => RequisitionStatus::PENDING_APPROVAL,
            'updated_by' => $userId,
        ]);

        return $this->find($requisition->id);
    }

    public function approve(PurchaseRequisition $requisition, int $userId): PurchaseRequisition
    {
        if ($requisition->status !== RequisitionStatus::PENDING_APPROVAL) {
            throw new RuntimeException('لا يمكن اعتماد الطلب إلا إذا كان في انتظار الاعتماد.');
        }

        return DB::transaction(function () use ($requisition, $userId): PurchaseRequisition {
            foreach ($requisition->items as $item) {
                $item->update([
                    'quantity_approved' => $item->quantity_requested,
                    'updated_by'        => $userId,
                ]);
            }

            $requisition->update([
                'status'           => RequisitionStatus::APPROVED,
                'approved_by'      => $userId,
                'approved_at'      => Carbon::now(),
                'rejection_reason' => null,
                'updated_by'       => $userId,
            ]);

            return $this->find($requisition->id);
        });
    }

    public function reject(PurchaseRequisition $requisition, string $rejectionReason, int $userId): PurchaseRequisition
    {
        if ($requisition->status !== RequisitionStatus::PENDING_APPROVAL) {
            throw new RuntimeException('لا يمكن رفض الطلب إلا إذا كان في انتظار الاعتماد.');
        }

        if (trim($rejectionReason) === '') {
            throw new InvalidArgumentException('يجب تقديم سبب واضح لرفض الطلب.');
        }

        $requisition->update([
            'status'           => RequisitionStatus::REJECTED,
            'rejection_reason' => $rejectionReason,
            'approved_by'      => null,
            'approved_at'      => null,
            'updated_by'       => $userId,
        ]);

        return $this->find($requisition->id);
    }

    /**
     * استعراض الفرز وقراءة الأرصدة واقتراح مسار التنفيذ الذكي والتغطية الجزئية
     */
    public function getTriageOverview(PurchaseRequisition $requisition, int $warehouseId): array
    {
        $requisition->loadMissing(['items.product', 'items.productUnit.unit']);

        $stockTableName = (new ProductStock)->getTable();
        $hasReservedColumn = Schema::hasColumn($stockTableName, 'reserved_quantity');

        // جلب مخزن التشغيل الافتراضي المعتمد
        $operatingWarehouse = Warehouse::operating()->where('is_active', true)->first();
        $defaultOperatingWarehouseId = $operatingWarehouse?->id;

        $triageItems = [];

        foreach ($requisition->items as $item) {
            $availableStockBase = 0.0000;
            $availableStockInItemUnit = 0.0000;
            $isMatched = $item->product_id !== null;

            if ($isMatched) {
                $stockQuery = ProductStock::where('warehouse_id', $warehouseId)
                    ->where('product_id', $item->product_id);

                if ($hasReservedColumn) {
                    $availableStockBase = (float) $stockQuery
                        ->selectRaw('COALESCE(SUM(quantity - reserved_quantity), 0) as available_stock')
                        ->value('available_stock');
                } else {
                    $availableStockBase = (float) $stockQuery->sum('quantity');
                }

                $conversionFactor = 1.0;
                if ($item->productUnit && (float) $item->productUnit->conversion_factor > 0) {
                    $conversionFactor = (float) $item->productUnit->conversion_factor;
                }

                $availableStockInItemUnit = $availableStockBase / $conversionFactor;
            }

            $remainingQuantity = (float) $item->remaining_quantity;
            $isChlorineOrWeightSerial = ($item->product?->tracking_type === 'by_weight_serial');
            $internalAction = $isChlorineOrWeightSerial ? 'transfer' : 'issue';

            $fulfillableQuantity = 0.0000;
            $shortageQuantity = $remainingQuantity;

            if ($isMatched && $remainingQuantity > 0) {
                $fulfillableQuantity = min($availableStockInItemUnit, $remainingQuantity);
                $fulfillableQuantity = max(0.0000, $fulfillableQuantity);
                $shortageQuantity = max(0.0000, $remainingQuantity - $fulfillableQuantity);
            }

            $suggestedAction = 'purchase';
            if ($remainingQuantity <= 0) {
                $suggestedAction = 'fulfilled';
            } elseif (! $isMatched || $availableStockInItemUnit <= 0) {
                $suggestedAction = 'purchase';
            } elseif ($availableStockInItemUnit >= $remainingQuantity) {
                $suggestedAction = $internalAction;
            } else {
                // رصيد جزئي متاح بالمخزن
                $suggestedAction = 'partial';
            }

            $canFulfillFully = $isMatched && $remainingQuantity > 0 && ($availableStockInItemUnit >= $remainingQuantity);
            $canFulfillPartially = $isMatched && $remainingQuantity > 0 && ($availableStockInItemUnit > 0) && ($availableStockInItemUnit < $remainingQuantity);

            $triageItems[] = [
                'requisition_item_id'       => $item->id,
                'item_name'                 => $item->product ? $item->product->name : $item->item_name,
                'is_free_text'              => ! $isMatched,
                'is_chlorine'               => $isChlorineOrWeightSerial,
                'product_id'                => $item->product_id,
                'product_unit_id'           => $item->product_unit_id,
                'quantity_requested'        => (float) $item->quantity_requested,
                'quantity_approved'         => (float) $item->quantity_approved,
                'quantity_ordered'          => (float) $item->quantity_ordered,
                'quantity_issued'           => (float) $item->quantity_issued,
                'quantity_transferred'      => (float) $item->quantity_transferred,
                'remaining_quantity'        => $remainingQuantity,
                'available_stock'           => round($availableStockInItemUnit, 4),
                'fulfillable_quantity'      => round($fulfillableQuantity, 4),
                'shortage_quantity'         => round($shortageQuantity, 4),
                'suggested_action'          => $suggestedAction,
                'suggested_internal_action' => $internalAction,
                'can_fulfill_from_stock'    => $canFulfillFully,
                'can_fulfill_partially'     => $canFulfillPartially,
                'can_issue_from_warehouse'  => $canFulfillFully || $canFulfillPartially,
                'suggested_to_warehouse_id' => $isChlorineOrWeightSerial ? $defaultOperatingWarehouseId : null,
            ];
        }

        return [
            'requisition_id'                 => $requisition->id,
            'requisition_number'             => $requisition->requisition_number,
            'warehouse_id'                   => $warehouseId,
            'default_operating_warehouse_id' => $defaultOperatingWarehouseId,
            'items'                          => $triageItems,
        ];
    }

    /**
     * تنفيذ التوزيع الذكي: إنشاء مسودات أذونات الصرف وأوامر التحويل المخزني بحسب طبيعة كل صنف مع دعم الفرز الجزئي
     */
    public function executeTriage(PurchaseRequisition $requisition, array $data, int $userId): array
    {
        if ($requisition->status !== RequisitionStatus::APPROVED) {
            throw new RuntimeException('لا يمكن فرز وتوزيع الطلب إلا إذا كان معتمداً.');
        }

        return DB::transaction(function () use ($requisition, $data, $userId): array {
            $warehouseId = (int) $data['warehouse_id'];
            $globalToWarehouseId = isset($data['to_warehouse_id']) ? (int) $data['to_warehouse_id'] : null;

            // 1. التحقق التراكمي المسبق من أن إجمالي الكميات المدخلة لكل بند لا يتجاوز الكمية المتبقية
            $itemAllocations = [];
            foreach ($data['items'] as $itemData) {
                $itemId = (int) $itemData['requisition_item_id'];
                $qty = (float) $itemData['quantity'];
                $itemAllocations[$itemId] = ($itemAllocations[$itemId] ?? 0.0) + $qty;
            }

            foreach ($itemAllocations as $itemId => $totalAllocatedQty) {
                /** @var PurchaseRequisitionItem $rItem */
                $rItem = $requisition->items()->lockForUpdate()->findOrFail($itemId);
                $remaining = (float) $rItem->remaining_quantity;

                if ($totalAllocatedQty > $remaining) {
                    throw new InvalidArgumentException(
                        "إجمالي الكميات الموزعة ({$totalAllocatedQty}) يتجاوز الكمية المتبقية المطلوبة ({$remaining}) للبند: {$rItem->item_name}"
                    );
                }
            }

            // 2. توزيع البنود بحسب الإجراءات المحددة
            $issueItemsPayload = [];
            $transferItemsPayload = [];
            $purchaseItemsSummary = [];

            foreach ($data['items'] as $itemData) {
                /** @var PurchaseRequisitionItem $requisitionItem */
                $requisitionItem = $requisition->items()->lockForUpdate()->findOrFail($itemData['requisition_item_id']);

                if (! empty($itemData['product_id'])) {
                    $requisitionItem->update([
                        'product_id'      => $itemData['product_id'],
                        'product_unit_id' => $itemData['product_unit_id'] ?? $requisitionItem->product_unit_id,
                        'updated_by'      => $userId,
                    ]);
                }

                $action = $itemData['action'];
                $quantity = (float) $itemData['quantity'];

                if ($quantity <= 0) {
                    throw new InvalidArgumentException("الكمية المحددة للبند '{$requisitionItem->item_name}' يجب أن تكون أكبر من الصفر.");
                }

                if ($action === 'issue') {
                    $productId = $requisitionItem->product_id ?? $itemData['product_id'] ?? null;
                    $productUnitId = $requisitionItem->product_unit_id ?? $itemData['product_unit_id'] ?? null;

                    if (! $productId || ! $productUnitId) {
                        throw new InvalidArgumentException("لا يمكن صرف البند '{$requisitionItem->item_name}' من المخزن دون مطابقته بصنف ووحدة رسمية.");
                    }

                    $issueItemsPayload[] = [
                        'requisition_item_id' => $requisitionItem->id,
                        'product_id'          => (int) $productId,
                        'product_unit_id'     => (int) $productUnitId,
                        'quantity'            => $quantity,
                        'unit_cost'           => isset($itemData['unit_cost']) ? (float) $itemData['unit_cost'] : (float) $requisitionItem->estimated_unit_cost,
                        'notes'               => $itemData['notes'] ?? $requisitionItem->notes,
                    ];
                } elseif ($action === 'transfer') {
                    $productId = $requisitionItem->product_id ?? $itemData['product_id'] ?? null;
                    $productUnitId = $requisitionItem->product_unit_id ?? $itemData['product_unit_id'] ?? null;

                    if (! $productId || ! $productUnitId) {
                        throw new InvalidArgumentException("لا يمكن تحويل البند '{$requisitionItem->item_name}' من المخزن دون مطابقته بصنف ووحدة رسمية.");
                    }

                    // تحديد المستودع الوجهة (مخزن التشغيل)
                    $targetWarehouseId = $itemData['to_warehouse_id'] ?? $globalToWarehouseId;
                    if (! $targetWarehouseId) {
                        $operatingWarehouse = Warehouse::operating()->where('is_active', true)->first();
                        $targetWarehouseId = $operatingWarehouse?->id;
                    }

                    if (! $targetWarehouseId) {
                        throw new InvalidArgumentException("لم يتم العثور على مخزن تشغيل نشط لتحويل البند: {$requisitionItem->item_name}");
                    }

                    if ((int) $targetWarehouseId === $warehouseId) {
                        throw new InvalidArgumentException("لا يمكن أن يكون مستودع التحويل الوجهة هو نفس المستودع المصدر للبند: {$requisitionItem->item_name}");
                    }

                    $transferItemsPayload[] = [
                        'requisition_item' => $requisitionItem,
                        'to_warehouse_id'  => (int) $targetWarehouseId,
                        'product_id'       => (int) $productId,
                        'product_unit_id'  => (int) $productUnitId,
                        'quantity'         => $quantity,
                        'unit_cost'        => isset($itemData['unit_cost']) ? (float) $itemData['unit_cost'] : (float) $requisitionItem->estimated_unit_cost,
                        'notes'            => $itemData['notes'] ?? $requisitionItem->notes,
                    ];
                } else {
                    $purchaseItemsSummary[] = [
                        'requisition_item_id' => $requisitionItem->id,
                        'item_name'           => $requisitionItem->product ? $requisitionItem->product->name : $requisitionItem->item_name,
                        'quantity'            => $quantity,
                    ];
                }
            }

            /** @var PurchaseIssue|null $draftIssue */
            $draftIssue = null;
            if (! empty($issueItemsPayload)) {
                $draftIssue = $this->issueService->createDraftFromRequisition(
                    $requisition,
                    $warehouseId,
                    $issueItemsPayload,
                    $userId
                );
            }

            /** @var Transfer|null $draftTransfer */
            $draftTransfer = null;
            if (! empty($transferItemsPayload)) {
                $destinationWarehouseId = $transferItemsPayload[0]['to_warehouse_id'];

                $transferItemsFormatted = [];
                foreach ($transferItemsPayload as $transferItem) {
                    $transferItemsFormatted[] = [
                        'product_id'      => $transferItem['product_id'],
                        'product_unit_id' => $transferItem['product_unit_id'],
                        'quantity'        => $transferItem['quantity'],
                        'unit_cost'       => $transferItem['unit_cost'],
                        'notes'           => $transferItem['notes'],
                    ];

                    /** @var PurchaseRequisitionItem $rItem */
                    $rItem = $transferItem['requisition_item'];
                    $rItem->increment('quantity_transferred', $transferItem['quantity'], [
                        'updated_by' => $userId,
                    ]);
                }

                $draftTransfer = $this->transferService->createTransfer([
                    'requisition_id'    => $requisition->id,
                    'from_warehouse_id' => $warehouseId,
                    'to_warehouse_id'   => $destinationWarehouseId,
                    'transfer_date'     => Carbon::now()->toDateString(),
                    'status'            => 'pending',
                    'notes'             => "أمر تحويل ناتج آلياً عن فرز طلب الاحتياج رقم: {$requisition->requisition_number}",
                    'items'             => $transferItemsFormatted,
                ], $userId);
            }

            $this->syncRequisitionStatus((int) $requisition->id, $userId);

            return [
                'requisition'             => $this->find($requisition->id),
                'draft_issue'             => $draftIssue,
                'draft_transfer'          => $draftTransfer,
                'issued_items_count'      => count($issueItemsPayload),
                'transferred_items_count' => count($transferItemsPayload),
                'purchase_items_count'    => count($purchaseItemsSummary),
            ];
        });
    }

    protected function generateRequisitionNumber(): string
    {
        $prefix = 'PR-' . Carbon::now()->format('Ymd');

        $lastRecord = PurchaseRequisition::withTrashed()
            ->where('requisition_number', 'like', "{$prefix}-%")
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if ($lastRecord && preg_match('/-(\d{4})$/', $lastRecord->requisition_number, $matches)) {
            $nextSequence = (int) $matches[1] + 1;
        } else {
            $nextSequence = 1;
        }

        return sprintf('%s-%04d', $prefix, $nextSequence);
    }

    protected function syncRequisitionStatus(int $requisitionId, int $userId): void
    {
        $requisition = PurchaseRequisition::with('items')->find($requisitionId);
        if (! $requisition || in_array($requisition->status, [RequisitionStatus::CANCELLED, RequisitionStatus::REJECTED], true)) {
            return;
        }

        $allFulfilled = $requisition->items->every(function ($item): bool {
            $targetQty = (float) ($item->quantity_approved > 0 ? $item->quantity_approved : $item->quantity_requested);
            $totalFulfilled = (float) $item->quantity_ordered + (float) $item->quantity_issued + (float) $item->quantity_transferred;
            return $totalFulfilled >= $targetQty;
        });

        if ($allFulfilled && $requisition->status !== RequisitionStatus::ORDERED) {
            $requisition->update([
                'status'     => RequisitionStatus::ORDERED,
                'updated_by' => $userId,
            ]);
        } elseif (! $allFulfilled && $requisition->status === RequisitionStatus::ORDERED) {
            $requisition->update([
                'status'     => RequisitionStatus::APPROVED,
                'updated_by' => $userId,
            ]);
        }
    }
}