<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Services;

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
use InvalidArgumentException;
use RuntimeException;

class PurchaseIssueService
{
    public function __construct(
        protected StockMovementService $stockMovementService
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
                $lineTotalCost = $quantity * $unitCost;
                $totalIssueCost += $lineTotalCost;

                $issue->items()->create([
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
            }

            $issue->update(['total_cost' => $totalIssueCost]);

            return $this->find($issue->id);
        });
    }

    public function update(PurchaseIssue $issue, array $data, int $userId): PurchaseIssue
    {
        if ($issue->status !== IssueStatus::DRAFT) {
            throw new RuntimeException('لا يمكن تعديل إذن الصرف إلا إذا كان في حالة مسودة.');
        }

        return DB::transaction(function () use ($issue, $data, $userId): PurchaseIssue {
            $issue->update([
                'warehouse_id'  => $data['warehouse_id'] ?? $issue->warehouse_id,
                'department_id' => array_key_exists('department_id', $data) ? $data['department_id'] : $issue->department_id,
                'recipient_id'  => array_key_exists('recipient_id', $data) ? $data['recipient_id'] : $issue->recipient_id,
                'issue_date'    => $data['issue_date'] ?? $issue->issue_date,
                'notes'         => array_key_exists('notes', $data) ? $data['notes'] : $issue->notes,
                'updated_by'    => $userId,
            ]);

            if (isset($data['items']) && is_array($data['items'])) {
                $submittedItemIds = collect($data['items'])
                    ->pluck('id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->toArray();

                $issue->items()
                    ->whereNotIn('id', $submittedItemIds)
                    ->delete();

                $totalIssueCost = 0.0000;

                foreach ($data['items'] as $itemData) {
                    $quantity = (float) $itemData['quantity'];
                    $unitCost = isset($itemData['unit_cost']) ? (float) $itemData['unit_cost'] : 0.0000;
                    $lineTotalCost = $quantity * $unitCost;
                    $totalIssueCost += $lineTotalCost;

                    if (! empty($itemData['id'])) {
                        /** @var PurchaseIssueItem|null $existingItem */
                        $existingItem = $issue->items()->find($itemData['id']);
                        if ($existingItem) {
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
                        }
                    } else {
                        $issue->items()->create([
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
                    }
                }

                $issue->update(['total_cost' => $totalIssueCost]);
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
            $issue->items()->delete();
            return (bool) $issue->delete();
        });
    }

    /**
     * تأكيد الصرف والخصم اللحظي من المخزن ومزامنة كميات وحالة طلب الاحتياج
     */
    public function confirmIssue(PurchaseIssue $issue, int $userId): PurchaseIssue
    {
        if ($issue->status !== IssueStatus::DRAFT) {
            throw new RuntimeException('لا يمكن تأكيد الصرف إلا للأذونات في حالة مسودة.');
        }

        $issue->loadMissing(['items.productUnit']);

        if ($issue->items->isEmpty()) {
            throw new RuntimeException('لا يمكن تأكيد إذن صرف لا يحتوي على أي بنود.');
        }

        return DB::transaction(function () use ($issue, $userId): PurchaseIssue {
            foreach ($issue->items as $item) {
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

    /**
     * إلغاء إذن الصرف وعكس الأثر المخزني وتخفيض الكميات المنصرفة من طلب الاحتياج
     */
    public function cancelIssue(PurchaseIssue $issue, int $userId): PurchaseIssue
    {
        if ($issue->status === IssueStatus::CANCELLED) {
            throw new RuntimeException('إذن الصرف ملغي مسبقاً.');
        }

        return DB::transaction(function () use ($issue, $userId): PurchaseIssue {
            $issue->loadMissing('items');

            if ($issue->status === IssueStatus::ISSUED) {
                $this->stockMovementService->clearDocumentMovements($issue);

                foreach ($issue->items as $item) {
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

    /**
     * إنشاء مسودة إذن صرف ناتجة آلياً عن فرز الطلب الداخلي
     */
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