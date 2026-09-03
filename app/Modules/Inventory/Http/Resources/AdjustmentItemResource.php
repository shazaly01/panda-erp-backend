<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdjustmentItemResource extends JsonResource
{
    /**
     * تحويل المورد إلى مصفوفة بيانات JSON.
     * تم تصحيح طريقة استخراج بيانات وحدة المنتَج (productUnit) لتجنب التعارض الكائناتي.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'adjustment_id' => $this->adjustment_id,
            'product_id' => $this->product_id,
            'batch_id' => $this->batch_id,
            'product_unit_id' => $this->product_unit_id,
            'current_quantity' => (float) $this->current_quantity,
            'actual_quantity' => (float) $this->actual_quantity,
            'quantity_difference' => (float) $this->quantity_difference,
            'unit_cost' => $this->unit_cost !== null ? (float) $this->unit_cost : null,
            'notes' => $this->notes,
            'product' => new ProductResource($this->whenLoaded('product')),
            'batch' => new StockBatchResource($this->whenLoaded('batch')),
            'product_unit' => $this->whenLoaded('productUnit', function () {
                return [
                    'id' => $this->productUnit->id,
                    'unit_id' => $this->productUnit->unit_id,
                    'unit_name' => $this->productUnit->relationLoaded('unit') ? $this->productUnit->unit?->name : null,
                    'unit_symbol' => $this->productUnit->relationLoaded('unit') ? $this->productUnit->unit?->symbol : null,
                    'conversion_factor' => (float) $this->productUnit->conversion_factor,
                ];
            }),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}