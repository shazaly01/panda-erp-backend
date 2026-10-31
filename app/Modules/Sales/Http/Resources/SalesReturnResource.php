<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'return_number' => $this->return_number,
            'sales_invoice_id' => $this->sales_invoice_id,
            'invoice_number' => $this->invoice?->invoice_number,
            'shift' => [
                'id' => $this->shift_id,
                'shift_number' => $this->shift?->shift_number,
            ],
            'return_date' => $this->return_date?->toIso8601String(),
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
            'status' => $this->status,
            'posting_status' => $this->posting_status,
            'posted_at' => $this->posted_at?->toIso8601String(),
            'refund_status' => $this->refund_status,
            'subtotal' => (float) $this->subtotal,
            'discount_amount' => (float) $this->discount_amount,
            'taxable_amount' => (float) $this->taxable_amount,
            'vat_amount' => (float) $this->vat_amount,
            'total_amount' => (float) $this->total_amount,
            'refunded_amount' => (float) $this->refunded_amount,
            'remaining_amount' => (float) $this->remaining_amount,
            'reason' => $this->reason,
            'notes' => $this->notes,
            'creator' => [
                'id' => $this->created_by,
                'name' => $this->creator?->name,
            ],
            'updater' => [
                'id' => $this->updated_by,
                'name' => $this->updater?->name,
            ],
            'items' => SalesReturnItemResource::collection($this->whenLoaded('items')),
            'refund_payments' => SalesReturnPaymentResource::collection($this->whenLoaded('refundPayments')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}