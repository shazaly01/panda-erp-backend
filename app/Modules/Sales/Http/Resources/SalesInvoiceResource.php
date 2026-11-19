<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $cashAmount = 0.0;
        $bankAmount = 0.0;

        if ($this->relationLoaded('payments')) {
            foreach ($this->payments as $payment) {
                if ($payment->payment_method === 'cash') {
                    $cashAmount += (float) $payment->amount;
                } elseif (in_array($payment->payment_method, ['card', 'bank_transfer'], true)) {
                    $bankAmount += (float) $payment->amount;
                }
            }
        }

        $cashAmount = round($cashAmount, 2);
        $bankAmount = round($bankAmount, 2);
        $remainingAmount = (float) $this->remaining_amount;

        $paymentParts = [];
        if ($cashAmount > 0) {
            $paymentParts[] = 'نقدي';
        }
        if ($bankAmount > 0) {
            $paymentParts[] = 'بنكي';
        }
        if ($remainingAmount > 0) {
            $paymentParts[] = 'آجل';
        }

        $paymentSummaryLabel = ! empty($paymentParts)
            ? implode(' + ', $paymentParts)
            : ($this->payment_status === 'paid' ? 'مدفوع' : 'غير مدفوع');

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'public_url' => $this->public_url,
            'pdf_url' => $this->pdf_url,
            'invoice_number' => $this->invoice_number,
            'reference_number' => $this->reference_number,
            'invoice_date' => $this->invoice_date?->toIso8601String(),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'partner_id' => $this->partner_id,
            'customer_name_text' => $this->customer_name_text,
            'partner' => [
                'id' => $this->partner_id,
                'name' => $this->partner?->name,
                'phone' => $this->partner?->phone,
                'tax_number' => $this->partner?->tax_number,
                'price_list_id' => $this->partner?->price_list_id,
                'price_list_name' => $this->partner?->priceList?->name,
            ],
            'branch_id' => $this->branch_id,
            'warehouse_id' => $this->warehouse_id,
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
            'payment_summary_lbl' => $paymentSummaryLabel,
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
            'cash_amount' => $cashAmount,
            'bank_amount' => $bankAmount,
            'paid_amount' => (float) $this->paid_amount,
            'remaining_amount' => $remainingAmount,
            'notes' => $this->notes,
            'terms_conditions' => $this->terms_conditions,
            'creator' => [
                'id' => $this->created_by,
                'name' => $this->creator?->full_name ?? $this->creator?->username,
            ],
            'updater' => [
                'id' => $this->updated_by,
                'name' => $this->updater?->full_name ?? $this->updater?->username,
            ],
            'items' => SalesInvoiceItemResource::collection($this->whenLoaded('items')),
            'payments' => SalesInvoicePaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}