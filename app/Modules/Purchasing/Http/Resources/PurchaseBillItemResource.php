<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Purchasing\Models\PurchaseBillItem
 */
class PurchaseBillItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bill_id' => $this->bill_id,
            'purchase_order_item_id' => $this->purchase_order_item_id,
            'order_item' => $this->whenLoaded('orderItem', function () {
                return [
                    'id' => $this->orderItem->id,
                    'quantity' => (float) $this->orderItem->quantity,
                    'billed_quantity' => (float) $this->orderItem->billed_quantity,
                    'unit_price' => (float) $this->orderItem->unit_price,
                ];
            }),
            'receipt_item_id' => $this->receipt_item_id,
            'receipt_item' => $this->whenLoaded('receiptItem', function () {
                return [
                    'id' => $this->receiptItem->id,
                    'receipt_id' => $this->receiptItem->receipt_id,
                    'quantity_received' => (float) $this->receiptItem->quantity_received,
                    'quantity_accepted' => (float) $this->receiptItem->quantity_accepted,
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
            'account_id' => $this->account_id,
            'account' => $this->whenLoaded('account', function () {
                return [
                    'id' => $this->account->id,
                    'code' => $this->account->code ?? null,
                    'name' => $this->account->name ?? null,
                ];
            }),
            'quantity' => (float) $this->quantity,
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