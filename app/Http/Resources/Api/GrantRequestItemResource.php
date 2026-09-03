<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GrantRequestItemResource extends JsonResource
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
            'grant_request_id' => $this->grant_request_id,
            'department_id' => $this->department_id,
            'department_name' => $this->whenLoaded('department', fn() => $this->department?->name),
            'department_code' => $this->whenLoaded('department', fn() => $this->department?->code),
            'item_name' => $this->item_name,
            'specifications' => $this->specifications,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'estimated_cost' => $this->estimated_cost ? (float) $this->estimated_cost : null,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}