<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Http\Resources;

use App\Modules\Purchasing\Enums\ReceiptStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Purchasing\Models\PurchaseReceipt
 */
class PurchaseReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'receipt_number' => $this->receipt_number,
            'purchase_order_id' => $this->purchase_order_id,
            'purchase_order' => $this->whenLoaded('purchaseOrder', function () {
                return [
                    'id' => $this->purchaseOrder->id,
                    'order_number' => $this->purchaseOrder->order_number ?? null,
                    'status' => $this->purchaseOrder->status?->value ?? null,
                ];
            }),
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->whenLoaded('supplier', function () {
                return [
                    'id' => $this->supplier->id,
                    'name' => $this->supplier->name ?? null,
                    'phone' => $this->supplier->phone ?? null,
                    'email' => $this->supplier->email ?? null,
                ];
            }),
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => $this->whenLoaded('warehouse', function () {
                return [
                    'id' => $this->warehouse->id,
                    'name' => $this->warehouse->name ?? null,
                    'code' => $this->warehouse->code ?? null,
                ];
            }),
            'receipt_date' => $this->receipt_date?->format('Y-m-d'),
            'status' => [
                'value' => $this->status instanceof ReceiptStatus ? $this->status->value : $this->status,
                'label' => $this->status instanceof ReceiptStatus ? $this->status->label() : null,
            ],
            'supplier_delivery_note' => $this->supplier_delivery_note,
            'waybill_number' => $this->waybill_number,
            'received_by' => $this->received_by,
            'receiver' => $this->whenLoaded('receiver', function () {
                return [
                    'id' => $this->receiver->id,
                    'name' => $this->receiver->name ?? null,
                    'email' => $this->receiver->email ?? null,
                ];
            }),
            'received_at' => $this->received_at?->toISOString(),
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
            'items_count' => $this->whenCounted('items', $this->items_count),
            'total_cost' => $this->whenLoaded('items', function () {
                return (float) $this->items->sum('total_cost');
            }),
            'items' => PurchaseReceiptItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}