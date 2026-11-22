<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalaryRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'   => $this->id,
            'name' => $this->name,
            'code' => $this->code,

            // تحويل الـ Enum إلى قيمته النصية للقراءة
            'category' => $this->category?->value,
            'type'     => $this->type?->value,

            // تحويل القيمة لرقم عشري لضمان الدقة
            'value' => $this->value !== null ? (float) $this->value : null,

            'percentage_of_code'         => $this->percentage_of_code,
            'formula_expression'         => $this->formula_expression,
            'account_mapping_key'        => $this->account_mapping_key,
            'credit_account_mapping_key' => $this->credit_account_mapping_key,

            // بيانات الحساب المحاسبي الأساسي / المدين (تظهر فقط إذا تم تحميل العلاقة)
            'account_details' => $this->whenLoaded('accountMapping', function () {
                $account = $this->accountMapping?->account;

                return [
                    'mapping_name' => $this->accountMapping?->name,
                    'account_id'   => $account?->id,
                    'account_name' => $account?->name,
                    'account_code' => $account?->code,
                ];
            }),

            // بيانات الحساب المحاسبي للطرف الدائن الخاص بمساهمات الشركة
            'credit_account_details' => $this->whenLoaded('creditAccountMapping', function () {
                $account = $this->creditAccountMapping?->account;

                return [
                    'mapping_name' => $this->creditAccountMapping?->name,
                    'account_id'   => $account?->id,
                    'account_name' => $account?->name,
                    'account_code' => $account?->code,
                ];
            }),

            'is_active'   => (bool) $this->is_active,
            'description' => $this->description,

            // يظهر الترتيب فقط إذا كانت القاعدة معروضة ضمن "هيكل راتب"
            'sequence' => $this->whenPivotLoaded('structure_rules', function () {
                return $this->pivot->sequence;
            }),
        ];
    }
}