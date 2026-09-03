<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Modules\Inventory\Http\Resources\ProductResource;

class ProductionOrderItemResource extends JsonResource
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
            'production_order_id' => $this->production_order_id,
            'raw_material_id' => $this->raw_material_id,
            'product_unit_id' => $this->product_unit_id,
            'batch_id' => $this->batch_id,
            'planned_quantity' => (float) $this->planned_quantity,
            'actual_quantity' => $this->actual_quantity !== null ? (float) $this->actual_quantity : null,
            'unit_cost' => $this->unit_cost !== null ? (float) $this->unit_cost : null,
            'total_cost' => $this->total_cost !== null ? (float) $this->total_cost : null,
            'notes' => $this->notes,
            'raw_material' => new ProductResource($this->whenLoaded('rawMaterial')),
            'product_unit' => $this->whenLoaded('productUnit'),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
