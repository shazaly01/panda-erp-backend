<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Http\Resources;

use App\Modules\Inventory\Http\Resources\ProductResource;
use App\Modules\Inventory\Http\Resources\StockBatchResource;
use App\Modules\Inventory\Http\Resources\WarehouseLocationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseIssueItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'issue_id' => $this->issue_id,
            'requisition_item_id' => $this->requisition_item_id,
            'product_id' => $this->product_id,
            'product' => new ProductResource($this->whenLoaded('product')),
            'product_unit_id' => $this->product_unit_id,
            'product_unit' => $this->whenLoaded('productUnit', fn () => [
                'id' => $this->productUnit->id,
                'product_id' => $this->productUnit->product_id,
                'unit_id' => $this->productUnit->unit_id,
                'conversion_factor' => (float) $this->productUnit->conversion_factor,
                'is_base_unit' => (bool) $this->productUnit->is_base_unit,
                'unit' => $this->productUnit->relationLoaded('unit') && $this->productUnit->unit !== null ? [
                    'id' => $this->productUnit->unit->id,
                    'name' => $this->productUnit->unit->name,
                    'code' => $this->productUnit->unit->code ?? null,
                ] : null,
            ]),
            'location_id' => $this->location_id,
            'location' => new WarehouseLocationResource($this->whenLoaded('location')),
            'batch_id' => $this->batch_id,
            'batch' => new StockBatchResource($this->whenLoaded('batch')),
            'quantity' => (float) $this->quantity,
            'unit_cost' => (float) $this->unit_cost,
            'total_cost' => (float) $this->total_cost,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}