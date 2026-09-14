<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Http\Resources;

use App\Modules\Purchasing\Enums\RequisitionPriority;
use App\Modules\Purchasing\Enums\RequisitionStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Purchasing\Models\PurchaseRequisition
 */
class PurchaseRequisitionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'requisition_number' => $this->requisition_number,
            'department_id' => $this->department_id,
            'department' => $this->whenLoaded('department', function () {
                return [
                    'id' => $this->department->id,
                    'name' => $this->department->name ?? null,
                ];
            }),
            'requested_by' => $this->requested_by,
            'requester' => $this->whenLoaded('requester', function () {
                return [
                    'id' => $this->requester->id,
                    'name' => $this->requester->name ?? null,
                    'email' => $this->requester->email ?? null,
                ];
            }),
            'request_date' => $this->request_date?->format('Y-m-d'),
            'required_date' => $this->required_date?->format('Y-m-d'),
            'priority' => [
                'value' => $this->priority instanceof RequisitionPriority ? $this->priority->value : $this->priority,
                'label' => $this->priority instanceof RequisitionPriority ? $this->priority->label() : null,
            ],
            'status' => [
                'value' => $this->status instanceof RequisitionStatus ? $this->status->value : $this->status,
                'label' => $this->status instanceof RequisitionStatus ? $this->status->label() : null,
            ],
            'rejection_reason' => $this->rejection_reason,
            'notes' => $this->notes,
            'approved_by' => $this->approved_by,
            'approver' => $this->whenLoaded('approver', function () {
                return [
                    'id' => $this->approver->id,
                    'name' => $this->approver->name ?? null,
                    'email' => $this->approver->email ?? null,
                ];
            }),
            'approved_at' => $this->approved_at?->toISOString(),
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name ?? null,
                ];
            }),
            'updated_by' => $this->updated_by,
            'updater' => $this->whenLoaded('updater', function () {
                return [
                    'id' => $this->updater->id,
                    'name' => $this->updater->name ?? null,
                ];
            }),
            'items_count' => $this->whenCounted('items', $this->items_count),
            'total_estimated_cost' => $this->whenLoaded('items', function () {
                return (float) $this->items->sum(function ($item) {
                    return ((float) $item->quantity_requested) * ((float) $item->estimated_unit_cost);
                });
            }),
            'items' => PurchaseRequisitionItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}