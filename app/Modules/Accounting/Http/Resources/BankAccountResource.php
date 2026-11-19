<?php

namespace App\Modules\Accounting\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currentBalance = ($this->relationLoaded('account') && $this->account)
            ? $this->account->getCurrentBalance()
            : 0.0;

        return [
            'id'             => $this->id,
            'bank_name'      => $this->bank_name,
            'account_name'   => $this->account_name,
            'account_number' => $this->account_number,
            'iban'           => $this->iban,
            'branch_id'      => $this->branch_id,
            'balance'        => $currentBalance,

            // العملة
            'currency'       => new CurrencyResource($this->whenLoaded('currency')),

            // الحساب المالي المرتبط
            'account'        => $this->whenLoaded('account', function () use ($currentBalance) {
                return [
                    'id'      => $this->account->id,
                    'code'    => $this->account->code, // مثلاً 102001
                    'name'    => $this->account->name,
                    'balance' => $currentBalance,
                ];
            }),

            'is_active'      => (bool) $this->is_active,
            'created_at'     => $this->created_at->toDateTimeString(),
        ];
    }
}