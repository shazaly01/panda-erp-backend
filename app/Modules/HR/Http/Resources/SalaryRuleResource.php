<?php

namespace App\Modules\HR\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalaryRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,

            // تحويل الـ Enum إلى قيمته النصية للقراءة
            'category' => $this->category?->value,
            'type' => $this->type?->value,

            // تحويل القيمة لرقم عشري لضمان الدقة
            'value' => $this->value !== null ? (float) $this->value : null,

            'percentage_of_code' => $this->percentage_of_code,
            'formula_expression' => $this->formula_expression,
            'account_mapping_key' => $this->account_mapping_key,

            // بيانات الحساب المحاسبي المرتبط (تظهر فقط إذا تم تحميل العلاقة)
            'account_details' => $this->whenLoaded('accountMapping', function () {
                // نتحقق أولاً من وجود التوجيه، ثم نتحقق من وجود الحساب الفعلي المرتبط به
                $account = $this->accountMapping?->account;

                return [
                    'mapping_name' => $this->accountMapping?->name,
                    'account_id'   => $account?->id,
                    'account_name' => $account?->name,
                    'account_code' => $account?->code,
                ];
            }),

            'is_active' => (bool) $this->is_active,
            'description' => $this->description,

            // --- نقطة هامة جداً ---
            // هذا السطر يظهر الترتيب فقط إذا كانت القاعدة معروضة ضمن "هيكل راتب"
            'sequence' => $this->whenPivotLoaded('structure_rules', function () {
                return $this->pivot->sequence;
            }),
        ];
    }
}
