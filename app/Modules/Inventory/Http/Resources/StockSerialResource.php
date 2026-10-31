<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Resources;

use App\Modules\Inventory\Enums\StockSerialStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Inventory\Models\StockSerial
 */
class StockSerialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $statusValue = $this->status instanceof StockSerialStatus 
            ? $this->status->value 
            : (string) $this->status;

        $statusLabel = $this->status instanceof StockSerialStatus 
            ? $this->status->label() 
            : null;

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', function () {
                return [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'sku' => $this->product->sku,
                    'tracking_type' => $this->product->tracking_type,
                ];
            }),
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => $this->whenLoaded('warehouse', function () {
                return [
                    'id' => $this->warehouse->id,
                    'name' => $this->warehouse->name,
                    'code' => $this->warehouse->code ?? null,
                ];
            }),
            'location_id' => $this->location_id,
            'location' => $this->whenLoaded('location', function () {
                return [
                    'id' => $this->location->id,
                    'name' => $this->location->name,
                    'code' => $this->location->code ?? null,
                ];
            }),
            'batch_id' => $this->batch_id,
            'batch' => $this->whenLoaded('batch', function () {
                return [
                    'id' => $this->batch->id,
                    'batch_number' => $this->batch->batch_number,
                ];
            }),
            'serial_number' => $this->serial_number,
            'tare_weight' => $this->tare_weight !== null ? (float) $this->tare_weight : null,
            'gross_weight' => $this->gross_weight !== null ? (float) $this->gross_weight : null,
            'current_gas_weight' => $this->current_gas_weight !== null ? (float) $this->current_gas_weight : null,
            'status' => $statusValue,
            'status_label' => $statusLabel,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}