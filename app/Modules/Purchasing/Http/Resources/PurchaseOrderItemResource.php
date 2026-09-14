<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Purchasing\Models\PurchaseOrderItem
 */
class PurchaseOrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_order_id' => $this->purchase_order_id,
            'requisition_item_id' => $this->requisition_item_id,
            'requisition_item' => $this->whenLoaded('requisitionItem', function () {
                return [
                    'id' => $this->requisitionItem->id,
                    'requisition_id' => $this->requisitionItem->requisition_id,
                    'quantity_requested' => (float) $this->requisitionItem->quantity_requested,
                    'quantity_approved' => (float) $this->requisitionItem->quantity_approved,
                ];
            }),
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', function () {
                return [
                    'id' => $this->product->id,
                    'name' => $this->product->name ?? null,
                    'code' => $this->product->code ?? null,
                    'sku' => $this->product->sku ?? null,
                ];
            }),
            'product_unit_id' => $this->product_unit_id,
            'product_unit' => $this->whenLoaded('productUnit', function () {
                return [
                    'id' => $this->productUnit->id,
                    'name' => $this->productUnit->name ?? null,
                ];
            }),
            'quantity' => (float) $this->quantity,
            'received_quantity' => (float) $this->received_quantity,
            'billed_quantity' => (float) $this->billed_quantity,
            'unit_price' => (float) $this->unit_price,
            'discount_percentage' => (float) $this->discount_percentage,
            'discount_amount' => (float) $this->discount_amount,
            'tax_rate' => (float) $this->tax_rate,
            'tax_amount' => (float) $this->tax_amount,
            'subtotal' => (float) $this->subtotal,
            'total' => (float) $this->total,
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