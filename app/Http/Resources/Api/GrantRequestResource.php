<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GrantRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_number' => $this->request_number,
            'target_organization' => $this->target_organization,
            'title' => $this->title,
            'request_date' => $this->request_date?->format('Y-m-d'),
            'status' => $this->status,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'creator_name' => $this->whenLoaded('creator', fn() => $this->creator?->name),
            'total_items_count' => $this->whenLoaded('items', fn() => $this->items->count()),
            'total_quantity' => $this->whenLoaded('items', fn() => $this->items->sum('quantity')),
            'total_estimated_cost' => $this->whenLoaded('items', fn() => (float) $this->items->sum('estimated_cost')),
            'items' => GrantRequestItemResource::collection($this->whenLoaded('items')),
            'departments_summary' => $this->whenLoaded('items', function () {
                return $this->items
                    ->groupBy('department_id')
                    ->map(function ($departmentItems) {
                        $firstItem = $departmentItems->first();
                        return [
                            'department_id' => $firstItem->department_id,
                            'department_name' => $firstItem->department?->name,
                            'department_code' => $firstItem->department?->code,
                            'items_count' => $departmentItems->count(),
                            'total_quantity' => $departmentItems->sum('quantity'),
                            'items' => GrantRequestItemResource::collection($departmentItems),
                        ];
                    })
                    ->values();
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}