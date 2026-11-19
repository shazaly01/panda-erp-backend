<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesInvoiceItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $warehouseId = $this->invoice?->warehouse_id;
        $currentStock = 0.0;

        if ($this->product && $this->product->relationLoaded('stocks') && $warehouseId) {
            $currentStock = (float) $this->product->stocks
                ->where('warehouse_id', $warehouseId)
                ->sum('quantity');
        }

        $availableUnits = [];
        if ($this->product && $this->product->relationLoaded('units')) {
            $availableUnits = $this->product->units->map(function ($productUnit): array {
                $prices = $productUnit->relationLoaded('prices')
                    ? $productUnit->prices->map(function ($priceRow): array {
                        return [
                            'id' => $priceRow->id,
                            'price_list_id' => $priceRow->price_list_id,
                            'price' => (float) $priceRow->price,
                            'min_quantity' => (float) $priceRow->min_quantity,
                        ];
                    })->values()->all()
                    : [];

                $defaultPrice = 0.0;
                if ($productUnit->relationLoaded('prices') && $productUnit->prices->isNotEmpty()) {
                    $defaultListPrice = $productUnit->prices->first(
                        fn ($p) => $p->relationLoaded('priceList') && $p->priceList?->is_default
                    );
                    $defaultPrice = (float) ($defaultListPrice?->price ?? $productUnit->prices->first()->price);
                }

                $barcodes = $productUnit->relationLoaded('barcodes')
                    ? $productUnit->barcodes->pluck('barcode')->filter()->values()->all()
                    : [];

                return [
                    'id' => $productUnit->id,
                    'unit_id' => $productUnit->unit_id,
                    'unit_name' => $productUnit->unit?->name,
                    'conversion_factor' => (float) $productUnit->conversion_factor,
                    'min_allowed_price' => (float) $productUnit->min_allowed_price,
                    'is_base_unit' => (bool) $productUnit->is_base_unit,
                    'is_sale_unit' => (bool) $productUnit->is_sale_unit,
                    'price' => $defaultPrice,
                    'prices' => $prices,
                    'barcodes' => $barcodes,
                ];
            })->values()->all();
        }

        return [
            'id' => $this->id,
            'sales_invoice_id' => $this->sales_invoice_id,
            'product_id' => $this->product_id,
            'product_name' => $this->product?->name,
            'product_code' => $this->product?->sku ?? $this->product?->code,
            'unit_id' => $this->unit_id,
            'unit_name' => $this->unit?->name,
            'quantity' => (float) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'cost_price' => (float) $this->cost_price,
            'subtotal' => (float) $this->subtotal,
            'discount_type' => $this->discount_type,
            'discount_value' => (float) $this->discount_value,
            'discount_amount' => (float) $this->discount_amount,
            'tax_rate' => (float) $this->tax_rate,
            'tax_amount' => (float) $this->tax_amount,
            'total_amount' => (float) $this->total_amount,
            'options' => $this->options,
            'notes' => $this->notes,
            'current_stock' => $currentStock,
            'available_units' => $availableUnits,
        ];
    }
}