<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Http\Resources;

use App\Modules\Purchasing\Enums\PurchaseReturnStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Purchasing\Models\PurchaseReturn
 */
class PurchaseReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'return_number' => $this->return_number,
            'bill_id' => $this->bill_id,
            'bill' => $this->whenLoaded('bill', function () {
                return [
                    'id' => $this->bill->id,
                    'bill_number' => $this->bill->bill_number ?? null,
                    'supplier_bill_number' => $this->bill->supplier_bill_number ?? null,
                    'status' => $this->bill->status?->value ?? null,
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
            'return_date' => $this->return_date?->format('Y-m-d'),
            'status' => [
                'value' => $this->status instanceof PurchaseReturnStatus ? $this->status->value : $this->status,
                'label' => $this->status instanceof PurchaseReturnStatus ? $this->status->label() : null,
            ],
            'return_reason' => $this->return_reason,
            'subtotal' => (float) $this->subtotal,
            'tax_amount' => (float) $this->tax_amount,
            'total_amount' => (float) $this->total_amount,
            'posted_by' => $this->posted_by,
            'poster' => $this->whenLoaded('poster', function () {
                return [
                    'id' => $this->poster->id,
                    'name' => $this->poster->name ?? null,
                    'email' => $this->poster->email ?? null,
                ];
            }),
            'posted_at' => $this->posted_at?->toISOString(),
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
            'items' => PurchaseReturnItemResource::collection($this->whenLoaded('items')),
            'stock_movements_count' => $this->whenCounted('stockMovements', $this->stock_movements_count),
            'journal_entries_count' => $this->whenCounted('journalEntries', $this->journal_entries_count),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}