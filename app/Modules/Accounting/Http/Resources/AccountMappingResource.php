<?php

namespace App\Modules\Accounting\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountMappingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name, // الاسم الظاهر (مثلاً: "الراتب الأساسي")
            'key' => $this->key,   // المفتاح البرمجي (مثلاً: "basic_salary")

            // بيانات الحساب المرتبط حالياً (إذا وجد)
            'account_id' => $this->account_id,
            'account_name' => $this->account ? $this->account->name : null,
            'account_code' => $this->account ? $this->account->code : null,

            'description' => $this->description,
        ];
    }
}
