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
        $isWeightSerial = $this->tracking_type === 'by_weight_serial';

        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'tracking_type' => $this->tracking_type,
            'matched_barcode' => $this->matched_barcode ?? null,
            'is_weight_barcode' => (bool) ($this->is_weight_barcode ?? false),
            'scanned_quantity' => (float) ($this->scanned_quantity ?? 1.0000),
            'available_quantity' => (float) ($this->available_quantity ?? 0.0000),

            // نطاق الكميات التي جهّزتها ProductService؛ null يعني جميع المخازن
            // عند تحميل الكميات، ولا نفترض مخزناً من المستند أو من طلب العرض.
            'quantity_warehouse_id' => ($this->quantity_warehouse_id ?? null) !== null
                ? (int) $this->quantity_warehouse_id
                : null,

            // الغاز من دفتر المخزون، وليس من عدد الأسطوانات أو قراءة سيريال ممسوح.
            // نبقي القيمة null إن لم تُحمّل، ولا نحوّل غياب المعلومة إلى صفر.
            'gas_stock_kg' => $isWeightSerial && ($this->gas_stock_kg ?? null) !== null
                ? (float) $this->gas_stock_kg
                : null,
            'gas_reserved_kg' => $isWeightSerial && ($this->gas_reserved_kg ?? null) !== null
                ? (float) $this->gas_reserved_kg
                : null,
            'gas_available_kg' => $isWeightSerial && ($this->gas_available_kg ?? null) !== null
                ? (float) $this->gas_available_kg
                : null,

            // العدد وتصنيفه محسوبان في الخدمة من سجلات الأسطوانات بالمخازن.
            // لا نجري هنا استعلامات إضافية أو نعيد حساب العدد من كمية الغاز.
            'cylinders_count' => $isWeightSerial && ($this->cylinders_count ?? null) !== null
                ? (int) $this->cylinders_count
                : null,
            'cylinders_by_status' => $isWeightSerial
                ? ($this->cylinders_by_status ?? null)
                : null,

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
