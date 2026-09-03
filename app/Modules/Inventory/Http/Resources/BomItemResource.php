<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BomItemResource extends JsonResource
{
    /**
     * تحويل المورد إلى مصفوفة بيانات JSON.
     * تم اعتماد المسميات المطابقة لجدول المايجريشن (raw_material_id و product_unit_id)
     * مع معالجة استخراج معلومات الوحدة بشكل آمن لمنع خلط الكائنات.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bom_id' => $this->bom_id,
            'raw_material_id' => $this->raw_material_id,
            'product_unit_id' => $this->product_unit_id,
            'quantity' => (float) $this->quantity,
            'waste_percentage' => $this->waste_percentage !== null ? (float) $this->waste_percentage : null,
            'notes' => $this->notes,
            'raw_material' => new ProductResource($this->whenLoaded('rawMaterial')),
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