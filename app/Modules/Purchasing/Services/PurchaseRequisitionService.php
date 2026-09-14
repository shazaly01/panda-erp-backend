<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Services;

use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Models\ProductUnit;
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
        protected PurchaseIssueService $issueService
    ) {}

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = PurchaseRequisition::query()
            ->with([
                'department',
                'requester',
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

            $requisition = PurchaseRequisition::create([
                'requisition_number' => $requisitionNumber,
                'department_id' => $data['department_id'] ?? null,
                'requested_by' => $userId,
                'request_date' => $data['request_date'] ?? Carbon::now()->toDateString(),
                'required_date' => $data['required_date'] ?? null,
                'priority' => $data['priority'],
                'status' => RequisitionStatus::DRAFT,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            foreach ($data['items'] as $itemData) {
                $requisition->items()->create([
                    'product_id' => $itemData['product_id'] ?? null,
                    'item_name' => $itemData['item_name'] ?? null,
                    'product_unit_id' => $itemData['product_unit_id'] ?? null,
                    'unit_name' => $itemData['unit_name'] ?? null,
                    'quantity_requested' => $itemData['quantity_requested'],
                    'quantity_approved' => 0.0000,
                    'quantity_ordered' => 0.0000,
                    'quantity_issued' => 0.0000,
                    'estimated_unit_cost' => $itemData['estimated_unit_cost'] ?? 0.0000,
                    'specifications' => $itemData['specifications'] ?? null,
                    'notes' => $itemData['notes'] ?? null,
                    'created_by' => $userId,
                    'updated_by' => $userId,
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
            $requisition->update([
                'department_id' => $data['department_id'] ?? $requisition->department_id,
                'request_date' => $data['request_date'] ?? $requisition->request_date,
                'required_date' => array_key_exists('required_date', $data) ? $data['required_date'] : $requisition->required_date,
                'priority' => $data['priority'] ?? $requisition->priority,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $requisition->notes,
                'updated_by' => $userId,
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
                                'product_id' => $itemData['product_id'] ?? null,
                                'item_name' => $itemData['item_name'] ?? null,
                                'product_unit_id' => $itemData['product_unit_id'] ?? null,
                                'unit_name' => $itemData['unit_name'] ?? null,
                                'quantity_requested' => $itemData['quantity_requested'],
                                'estimated_unit_cost' => $itemData['estimated_unit_cost'] ?? $existingItem->estimated_unit_cost,
                                'specifications' => $itemData['specifications'] ?? null,
                                'notes' => $itemData['notes'] ?? null,
                                'updated_by' => $userId,
                            ]);
                        }
                    } else {
                        $requisition->items()->create([
                            'product_id' => $itemData['product_id'] ?? null,
                            'item_name' => $itemData['item_name'] ?? null,
                            'product_unit_id' => $itemData['product_unit_id'] ?? null,
                            'unit_name' => $itemData['unit_name'] ?? null,
                            'quantity_requested' => $itemData['quantity_requested'],
                            'quantity_approved' => 0.0000,
                            'quantity_ordered' => 0.0000,
                            'quantity_issued' => 0.0000,
                            'estimated_unit_cost' => $itemData['estimated_unit_cost'] ?? 0.0000,
                            'specifications' => $itemData['specifications'] ?? null,
                            'notes' => $itemData['notes'] ?? null,
                            'created_by' => $userId,
                            'updated_by' => $userId,
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
            'status' => RequisitionStatus::PENDING_APPROVAL,
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
                    'updated_by' => $userId,
                ]);
            }

            $requisition->update([
                'status' => RequisitionStatus::APPROVED,
                'approved_by' => $userId,
                'approved_at' => Carbon::now(),
                'rejection_reason' => null,
                'updated_by' => $userId,
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
            'status' => RequisitionStatus::REJECTED,
            'rejection_reason' => $rejectionReason,
            'approved_by' => null,
            'approved_at' => null,
            'updated_by' => $userId,
        ]);

        return $this->find($requisition->id);
    }

    /**
     * استعراض الفرز الذكي وقراءة الأرصدة اللحظية للمستودع واقتراح مسار التنفيذ
     */
    public function getTriageOverview(PurchaseRequisition $requisition, int $warehouseId): array
    {
        $requisition->loadMissing(['items.product', 'items.productUnit.unit']);

        $stockTableName = (new ProductStock)->getTable();
        $hasReservedColumn = Schema::hasColumn($stockTableName, 'reserved_quantity');

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

            $suggestedAction = 'purchase';
            if ($remainingQuantity <= 0) {
                $suggestedAction = 'fulfilled';
            } elseif ($isMatched && $availableStockInItemUnit >= $remainingQuantity) {
                $suggestedAction = 'issue';
            }

            $triageItems[] = [
                'requisition_item_id'      => $item->id,
                'item_name'                => $item->product ? $item->product->name : $item->item_name,
                'is_free_text'             => ! $isMatched,
                'product_id'               => $item->product_id,
                'product_unit_id'          => $item->product_unit_id,
                'quantity_requested'       => (float) $item->quantity_requested,
                'quantity_approved'        => (float) $item->quantity_approved,
                'quantity_ordered'         => (float) $item->quantity_ordered,
                'quantity_issued'          => (float) $item->quantity_issued,
                'remaining_quantity'       => $remainingQuantity,
                'available_stock'          => round($availableStockInItemUnit, 4),
                'suggested_action'         => $suggestedAction,
                'can_issue_from_warehouse' => $isMatched && $remainingQuantity > 0 && $availableStockInItemUnit >= $remainingQuantity,
            ];
        }

        return [
            'requisition_id'     => $requisition->id,
            'requisition_number' => $requisition->requisition_number,
            'warehouse_id'       => $warehouseId,
            'items'              => $triageItems,
        ];
    }

    /**
     * تنفيذ التوزيع الآلي: إنشاء مسودة إذن صرف مخزني للبنود المخصصة للمخزن
     */
    public function executeTriage(PurchaseRequisition $requisition, array $data, int $userId): array
    {
        if ($requisition->status !== RequisitionStatus::APPROVED) {
            throw new RuntimeException('لا يمكن فرز وتوزيع الطلب إلا إذا كان معتمداً.');
        }

        return DB::transaction(function () use ($requisition, $data, $userId): array {
            $warehouseId = (int) $data['warehouse_id'];
            $issueItemsPayload = [];
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

                if ($action === 'issue') {
                    if ($quantity <= 0) {
                        throw new InvalidArgumentException("الكمية المحددة للصرف للبند '{$requisitionItem->item_name}' يجب أن تكون أكبر من الصفر.");
                    }

                    if ($quantity > (float) $requisitionItem->remaining_quantity) {
                        throw new InvalidArgumentException("الكمية المحددة للصرف ({$quantity}) تتجاوز الكمية المتبقية المطلوبة ({$requisitionItem->remaining_quantity}) للبند: {$requisitionItem->item_name}");
                    }

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

            return [
                'requisition'          => $this->find($requisition->id),
                'draft_issue'          => $draftIssue,
                'issued_items_count'   => count($issueItemsPayload),
                'purchase_items_count' => count($purchaseItemsSummary),
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
}