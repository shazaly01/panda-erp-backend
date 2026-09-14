<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Purchasing\Models\PurchaseReceiptItem
 */
class PurchaseReceiptItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'receipt_id' => $this->receipt_id,
            'purchase_order_item_id' => $this->purchase_order_item_id,
            'order_item' => $this->whenLoaded('orderItem', function () {
                return [
                    'id' => $this->orderItem->id,
                    'purchase_order_id' => $this->orderItem->purchase_order_id,
                    'quantity' => (float) $this->orderItem->quantity,
                    'received_quantity' => (float) $this->orderItem->received_quantity,
                    'unit_price' => (float) $this->orderItem->unit_price,
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
            'location_id' => $this->location_id,
            'location' => $this->whenLoaded('location', function () {
                return [
                    'id' => $this->location->id,
                    'name' => $this->location->name ?? null,
                    'code' => $this->location->code ?? null,
                ];
            }),
            'batch_id' => $this->batch_id,
            'batch' => $this->whenLoaded('batch', function () {
                return [
                    'id' => $this->batch->id,
                    'batch_number' => $this->batch->batch_number ?? null,
                    'expiry_date' => $this->batch->expiry_date?->format('Y-m-d') ?? null,
                ];
            }),
            'quantity_received' => (float) $this->quantity_received,
            'quantity_accepted' => (float) $this->quantity_accepted,
            'quantity_rejected' => (float) $this->quantity_rejected,
            'unit_cost' => (float) $this->unit_cost,
            'total_cost' => (float) $this->total_cost,
            'rejection_reason' => $this->rejection_reason,
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