<?php

namespace App\Modules\Accounting\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoucherDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'amount'      => (float) $this->amount,
            'description' => $this->description,

            // بيانات الحساب (مهم جداً للمحاسب)
            'account'     => $this->whenLoaded('account', function () {
                return [
                    'id'   => $this->account->id,
                    'code' => $this->account->code,
                    'name' => $this->account->name,
                    // صيغة مفيدة للقوائم المنسدلة: "101001 - مصروفات كهرباء"
                    'full_name' => $this->account->code . ' - ' . $this->account->name,
                ];
            }),

            // مركز التكلفة (المشروع)
            'cost_center' => $this->whenLoaded('costCenter', function () {
                return [
                    'id'   => $this->costCenter->id,
                    'name' => $this->costCenter->name,
                ];
            }),
        ];
    }
}
