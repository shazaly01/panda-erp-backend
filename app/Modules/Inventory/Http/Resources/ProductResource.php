<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * تحويل الكيان إلى مصفوفة JSON مُنسقة للواجهات
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'category_name' => $this->whenLoaded('category', fn () => $this->category?->name),
            'name' => $this->name,
            'sku' => $this->sku,
            'description' => $this->description,
            'type' => $this->type,
            'inventory_policy' => $this->inventory_policy,
            'tracking_type' => $this->tracking_type,
            'valuation_method' => $this->valuation_method,
            'cost_price' => $this->cost_price !== null ? (float) $this->cost_price : 0.0,
            'is_active' => (bool) $this->is_active,

            // الرصيد المخزني المتاح للمستودع المحدد
            'current_stock' => $this->when(
                isset($this->current_stock),
                fn () => (float) $this->current_stock,
                fn () => (float) ($this->stock ?? $this->product_stocks_sum_quantity ?? 0.0)
            ),
            'stock' => $this->when(
                isset($this->current_stock),
                fn () => (float) $this->current_stock,
                fn () => (float) ($this->stock ?? $this->product_stocks_sum_quantity ?? 0.0)
            ),

            // الوحدات والأسعار والباركودات
            'units' => $this->whenLoaded('units', function () {
                return $this->units->map(function ($productUnit) {
                    return [
                        'id' => $productUnit->id,
                        'unit_id' => $productUnit->unit_id,
                        'unit_name' => $productUnit->relationLoaded('unit') ? $productUnit->unit?->name : null,
                        'conversion_factor' => (float) $productUnit->conversion_factor,
                        'is_base_unit' => (bool) $productUnit->is_base_unit,
                        'is_purchase_unit' => (bool) $productUnit->is_purchase_unit,
                        'is_sale_unit' => (bool) $productUnit->is_sale_unit,
                        'prices' => $productUnit->relationLoaded('prices')
                            ? $productUnit->prices->map(function ($price) {
                                return [
                                    'id' => $price->id,
                                    'price_list_id' => $price->price_list_id,
                                    'price_list_name' => $price->relationLoaded('priceList') ? $price->priceList?->name : null,
                                    'price' => (float) $price->price,
                                    'min_quantity' => (float) $price->min_quantity,
                                ];
                            })
                            : [],
                        'barcodes' => $productUnit->relationLoaded('barcodes')
                            ? $productUnit->barcodes->pluck('barcode')
                            : [],
                    ];
                });
            }),

            // قواعد إعادة الطلب المربوطة بالمستودعات
            'reorder_rules' => $this->whenLoaded('reorderRules', function () {
                return $this->reorderRules->map(function ($rule) {
                    return [
                        'id' => $rule->id,
                        'warehouse_id' => $rule->warehouse_id,
                        'warehouse_name' => $rule->relationLoaded('warehouse') ? $rule->warehouse?->name : null,
                        'min_quantity' => (float) $rule->min_quantity,
                        'max_quantity' => (float) $rule->max_quantity,
                        'reorder_quantity' => (float) $rule->reorder_quantity,
                        'is_active' => (bool) $rule->is_active,
                    ];
                });
            }),

            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}