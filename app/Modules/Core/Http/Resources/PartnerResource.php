<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Core\Models\Partner
 */
class PartnerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'partner_code' => $this->partner_code,
            'type' => $this->type?->value ?? $this->type,
            'name' => $this->name,
            'commercial_name' => $this->commercial_name,
            'tax_number' => $this->tax_number,
            'commercial_registry' => $this->commercial_registry,
            'tax_treatment' => $this->tax_treatment?->value ?? $this->tax_treatment,
            'is_customer' => (bool) $this->is_customer,
            'is_supplier' => (bool) $this->is_supplier,
            'status' => $this->status?->value ?? $this->status,
            'credit_limit' => (float) $this->credit_limit,
            'credit_period_days' => (int) $this->credit_period_days,
            'currency_id' => $this->currency_id,
            'currency' => $this->whenLoaded('currency', function () {
                return [
                    'id' => $this->currency->id,
                    'code' => $this->currency->code,
                    'name' => $this->currency->name,
                    'symbol' => $this->currency->symbol,
                ];
            }),
            'receivable_account_id' => $this->receivable_account_id,
            'receivable_account' => $this->whenLoaded('receivableAccount', function () {
                return [
                    'id' => $this->receivableAccount->id,
                    'code' => $this->receivableAccount->code,
                    'name' => $this->receivableAccount->name,
                ];
            }),
            'payable_account_id' => $this->payable_account_id,
            'payable_account' => $this->whenLoaded('payableAccount', function () {
                return [
                    'id' => $this->payableAccount->id,
                    'code' => $this->payableAccount->code,
                    'name' => $this->payableAccount->name,
                ];
            }),
            'bank_name' => $this->bank_name,
            'bank_account_number' => $this->bank_account_number,
            'iban' => $this->iban,
            'swift_code' => $this->swift_code,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}