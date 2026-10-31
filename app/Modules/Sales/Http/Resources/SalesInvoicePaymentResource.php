<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesInvoicePaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_number' => $this->payment_number,
            'sales_invoice_id' => $this->sales_invoice_id,
            'shift_id' => $this->shift_id,
            'payment_method' => $this->payment_method,
            'amount' => (float) $this->amount,
            'currency' => [
                'id' => $this->currency_id,
                'name' => $this->currency?->name,
                'code' => $this->currency?->code,
                'exchange_rate' => (float) $this->exchange_rate,
            ],
            'box' => [
                'id' => $this->box_id,
                'name' => $this->box?->name,
            ],
            'bank_account' => [
                'id' => $this->bank_account_id,
                'name' => $this->bankAccount?->name,
            ],
            'transaction_reference' => $this->transaction_reference,
            'payment_date' => $this->payment_date?->toIso8601String(),
            'notes' => $this->notes,
            'creator' => [
                'id' => $this->created_by,
                'name' => $this->creator?->name,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}