<?php

namespace App\Modules\Accounting\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BoxResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currentBalance = ($this->relationLoaded('account') && $this->account)
            ? $this->account->getCurrentBalance()
            : 0.0;

        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'branch_id'   => $this->branch_id,
            'balance'     => $currentBalance,

            // بيانات العملة (إذا تم تحميلها)
            'currency'    => new CurrencyResource($this->whenLoaded('currency')),

            // أهم جزء: بيانات الحساب المالي المرتبط
            'account'     => $this->whenLoaded('account', function () use ($currentBalance) {
                return [
                    'id'      => $this->account->id,
                    'code'    => $this->account->code, // مثلاً 101001
                    'name'    => $this->account->name,
                    'balance' => $currentBalance,
                ];
            }),

            'is_active'   => (bool) $this->is_active,
            'created_at'  => $this->created_at->toDateTimeString(),
        ];
    }
}