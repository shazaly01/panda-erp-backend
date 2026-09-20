<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosProductResource extends JsonResource
{
    /**
     * تحويل بيانات الصنف إلى هيكل نقطة البيع فائق السرعة
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $selectedUnit = $this->matched_unit ?? $this->units?->first();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'matched_barcode' => $this->matched_barcode ?? null,
            'is_weight_barcode' => (bool) ($this->is_weight_barcode ?? false),
            'scanned_quantity' => (float) ($this->scanned_quantity ?? 1.0000),
            'available_quantity' => (float) ($this->available_quantity ?? 0.0000),
            'unit' => $selectedUnit ? [
                'id' => $selectedUnit->id,
                'unit_id' => $selectedUnit->unit_id,
                'name' => $selectedUnit->unit?->name ?? null,
                'code' => $selectedUnit->unit?->code ?? null,
                'conversion_factor' => (float) $selectedUnit->conversion_factor,
            ] : null,
            'price' => (float) ($this->resolved_price ?? 0.0000),
            'available_units' => $this->units ? $this->units->map(function ($unit): array {
                return [
                    'id' => $unit->id,
                    'unit_id' => $unit->unit_id,
                    'name' => $unit->unit?->name ?? null,
                    'code' => $unit->unit?->code ?? null,
                    'conversion_factor' => (float) $unit->conversion_factor,
                    'is_base_unit' => (bool) $unit->is_base_unit,
                    'is_sale_unit' => (bool) $unit->is_sale_unit,
                ];
            })->values()->all() : [],
        ];
    }
}