<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Modules\Inventory\Http\Resources\ProductResource;

class ProductionOrderResource extends JsonResource
{
    /**
     * تحويل المورد إلى مصفوفة بيانات JSON.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'bom_id' => $this->bom_id,
            'product_id' => $this->product_id,
            'warehouse_id' => $this->warehouse_id,
            'planned_quantity' => (float) $this->planned_quantity,
            'actual_quantity' => $this->actual_quantity !== null ? (float) $this->actual_quantity : null,
            'status' => $this->status,
            'production_date' => $this->production_date?->toDateString(),
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'approved_by' => $this->approved_by,
            'bom' => $this->whenLoaded('bom'),
            'product' => new ProductResource($this->whenLoaded('product')),
            'warehouse' => $this->whenLoaded('warehouse'),
            'creator' => $this->whenLoaded('creator'),
            'approver' => $this->whenLoaded('approver'),
            'items' => ProductionOrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
