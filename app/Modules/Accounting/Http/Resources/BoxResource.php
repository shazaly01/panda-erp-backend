<?php

namespace App\Modules\Accounting\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BoxResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'branch_id'   => $this->branch_id,

            // بيانات العملة (إذا تم تحميلها)
            'currency'    => new CurrencyResource($this->whenLoaded('currency')),

            // أهم جزء: بيانات الحساب المالي المرتبط
            'account'     => $this->whenLoaded('account', function () {
                return [
                    'id'   => $this->account->id,
                    'code' => $this->account->code, // مثلاً 101001
                    'name' => $this->account->name,
                ];
            }),

            'is_active'   => (bool) $this->is_active,
            'created_at'  => $this->created_at->toDateTimeString(),
        ];
    }
}
