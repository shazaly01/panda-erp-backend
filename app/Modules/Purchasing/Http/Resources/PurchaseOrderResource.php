<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Http\Resources;

use App\Modules\Purchasing\Enums\PurchaseOrderStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Purchasing\Models\PurchaseOrder
 */
class PurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->whenLoaded('supplier', function () {
                return [
                    'id' => $this->supplier->id,
                    'name' => $this->supplier->name ?? null,
                    'phone' => $this->supplier->phone ?? null,
                    'email' => $this->supplier->email ?? null,
                ];
            }),
            'requisition_id' => $this->requisition_id,
            'requisition' => $this->whenLoaded('requisition', function () {
                return [
                    'id' => $this->requisition->id,
                    'requisition_number' => $this->requisition->requisition_number ?? null,
                    'status' => $this->requisition->status?->value ?? null,
                ];
            }),
            'currency_id' => $this->currency_id,
            'currency' => $this->whenLoaded('currency', function () {
                return [
                    'id' => $this->currency->id,
                    'name' => $this->currency->name ?? null,
                    'code' => $this->currency->code ?? null,
                    'symbol' => $this->currency->symbol ?? null,
                ];
            }),
            'exchange_rate' => (float) $this->exchange_rate,
            'order_date' => $this->order_date?->format('Y-m-d'),
            'expected_delivery_date' => $this->expected_delivery_date?->format('Y-m-d'),
            'payment_terms' => $this->payment_terms,
            'status' => [
                'value' => $this->status instanceof PurchaseOrderStatus ? $this->status->value : $this->status,
                'label' => $this->status instanceof PurchaseOrderStatus ? $this->status->label() : null,
            ],
            'subtotal' => (float) $this->subtotal,
            'discount_type' => $this->discount_type,
            'discount_value' => (float) $this->discount_value,
            'discount_amount' => (float) $this->discount_amount,
            'tax_amount' => (float) $this->tax_amount,
            'shipping_cost' => (float) $this->shipping_cost,
            'total_amount' => (float) $this->total_amount,
            'confirmed_by' => $this->confirmed_by,
            'confirmer' => $this->whenLoaded('confirmer', function () {
                return [
                    'id' => $this->confirmer->id,
                    'name' => $this->confirmer->name ?? null,
                    'email' => $this->confirmer->email ?? null,
                ];
            }),
            'confirmed_at' => $this->confirmed_at?->toISOString(),
            'notes' => $this->notes,
            'terms_and_conditions' => $this->terms_and_conditions,
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
            'items_count' => $this->whenCounted('items', $this->items_count),
            'items' => PurchaseOrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}