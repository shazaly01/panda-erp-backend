<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Purchasing\Models\PurchaseRequisitionItem
 */
class PurchaseRequisitionItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $quantityRequested = (float) $this->quantity_requested;
        $estimatedUnitCost = (float) $this->estimated_unit_cost;
        $estimatedTotalCost = round($quantityRequested * $estimatedUnitCost, 4);

        return [
            'id' => $this->id,
            'requisition_id' => $this->requisition_id,
            'product_id' => $this->product_id,
            'item_name' => $this->item_name,
            'display_name' => $this->product?->name ?? $this->item_name,
            'product' => $this->whenLoaded('product', function () {
                if (! $this->product) {
                    return null;
                }

                return [
                    'id' => $this->product->id,
                    'name' => $this->product->name ?? null,
                    'code' => $this->product->code ?? null,
                    'sku' => $this->product->sku ?? null,
                ];
            }),
            'product_unit_id' => $this->product_unit_id,
            'unit_name' => $this->unit_name,
            'product_unit' => $this->whenLoaded('productUnit', function () {
                if (! $this->productUnit) {
                    return null;
                }

                return [
                    'id' => $this->productUnit->id,
                    'name' => $this->productUnit->name ?? null,
                ];
            }),
            'quantity_requested' => $quantityRequested,
            'quantity_approved' => (float) $this->quantity_approved,
            'quantity_ordered' => (float) $this->quantity_ordered,
            'estimated_unit_cost' => $estimatedUnitCost,
            'estimated_total_cost' => $estimatedTotalCost,
            'specifications' => $this->specifications,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name ?? null,
                ];
            }),
            'updated_by' => $this->updated_by,
            'updater' => $this->whenLoaded('updater', function () {
                return [
                    'id' => $this->updater->id,
                    'name' => $this->updater->name ?? null,
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}