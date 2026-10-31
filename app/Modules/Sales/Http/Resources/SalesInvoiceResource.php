<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'reference_number' => $this->reference_number,
            'invoice_date' => $this->invoice_date?->toIso8601String(),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'partner' => [
                'id' => $this->partner_id,
                'name' => $this->partner?->name,
                'phone' => $this->partner?->phone,
                'tax_number' => $this->partner?->tax_number,
            ],
            'branch_id' => $this->branch_id,
            'warehouse' => [
                'id' => $this->warehouse_id,
                'name' => $this->warehouse?->name,
            ],
            'cost_center' => [
                'id' => $this->cost_center_id,
                'name' => $this->costCenter?->name,
            ],
            'currency' => [
                'id' => $this->currency_id,
                'name' => $this->currency?->name,
                'code' => $this->currency?->code,
                'exchange_rate' => (float) $this->exchange_rate,
            ],
            'shift' => [
                'id' => $this->shift_id,
                'shift_number' => $this->shift?->shift_number,
            ],
            'channel' => $this->channel,
            'order_type' => $this->order_type,
            'table_label' => $this->table_label,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'posting_status' => $this->posting_status,
            'posted_at' => $this->posted_at?->toIso8601String(),
            'subtotal' => (float) $this->subtotal,
            'discount_type' => $this->discount_type,
            'discount_value' => (float) $this->discount_value,
            'discount_amount' => (float) $this->discount_amount,
            'taxable_amount' => (float) $this->taxable_amount,
            'vat_amount' => (float) $this->vat_amount,
            'service_charge' => (float) $this->service_charge,
            'delivery_fee' => (float) $this->delivery_fee,
            'total_amount' => (float) $this->total_amount,
            'paid_amount' => (float) $this->paid_amount,
            'remaining_amount' => (float) $this->remaining_amount,
            'notes' => $this->notes,
            'terms_conditions' => $this->terms_conditions,
            'creator' => [
                'id' => $this->created_by,
                'name' => $this->creator?->name,
            ],
            'updater' => [
                'id' => $this->updated_by,
                'name' => $this->updater?->name,
            ],
            'items' => SalesInvoiceItemResource::collection($this->whenLoaded('items')),
            'payments' => SalesInvoicePaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}