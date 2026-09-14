<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Purchasing\Models\PurchaseReturnItem
 */
class PurchaseReturnItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'return_id' => $this->return_id,
            'bill_item_id' => $this->bill_item_id,
            'bill_item' => $this->whenLoaded('billItem', function () {
                return [
                    'id' => $this->billItem->id,
                    'bill_id' => $this->billItem->bill_id,
                    'quantity' => (float) $this->billItem->quantity,
                    'unit_price' => (float) $this->billItem->unit_price,
                    'total' => (float) $this->billItem->total,
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
            'quantity' => (float) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'tax_rate' => (float) $this->tax_rate,
            'tax_amount' => (float) $this->tax_amount,
            'subtotal' => (float) $this->subtotal,
            'total' => (float) $this->total,
            'reason' => $this->reason,
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