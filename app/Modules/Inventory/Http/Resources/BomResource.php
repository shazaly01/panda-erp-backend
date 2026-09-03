<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BomItemResource extends JsonResource
{
    /**
     * تحويل المورد إلى مصفوفة بيانات JSON.
     * تم تصحيح مسميات الحقول والعلاقات إلى raw_material_id و product_unit_id لتطابق الـ Model و الـ Migration
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
            'product_unit' => new UnitResource($this->whenLoaded('productUnit')),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}