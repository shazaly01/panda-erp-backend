<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Http\Resources;

use App\Modules\Inventory\Http\Resources\WarehouseResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseIssueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'issue_number' => $this->issue_number,
            'requisition_id' => $this->requisition_id,
            'requisition' => new PurchaseRequisitionResource($this->whenLoaded('requisition')),
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => new WarehouseResource($this->whenLoaded('warehouse')),
            'department_id' => $this->department_id,
            'department' => $this->whenLoaded('department', fn () => [
                'id' => $this->department->id,
                'name' => $this->department->name,
            ]),
            'recipient_id' => $this->recipient_id,
            'recipient' => $this->whenLoaded('recipient', fn () => [
                'id' => $this->recipient->id,
                'name' => $this->recipient->name,
            ]),
            'issue_date' => $this->issue_date?->format('Y-m-d'),
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'total_cost' => (float) $this->total_cost,
            'issued_by' => $this->issued_by,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'issuer' => $this->whenLoaded('issuer', fn () => [
                'id' => $this->issuer->id,
                'name' => $this->issuer->name,
            ]),
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'items' => PurchaseIssueItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenCounted('items'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}