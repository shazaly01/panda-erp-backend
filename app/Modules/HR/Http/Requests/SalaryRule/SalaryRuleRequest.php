<?php

namespace App\Modules\HR\Http\Requests\SalaryRule;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use App\Modules\HR\Enums\SalaryRuleCategory;
use App\Modules\HR\Enums\SalaryRuleType;

class SalaryRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        // نرجع true لأن التحقق يتم في الـ Policy المستدعى من المتحكم
        return true;
    }

    public function rules(): array
    {
        /** * نقطة حاسمة: الحصول على المعرف للتعديل
         * في الـ Controller أنت سميت المعرف 'salary_rule'
         */
        $rule = $this->route('salary_rule');
        // التأكد من استخراج الرقم التعريفي سواء كان المسار يمرر كائناً أو رقماً
        $ruleId = is_object($rule) ? $rule->id : $rule;

        return [
            'name' => ['required', 'string', 'max:255'],

            // هنا يكمن سر نجاح التعديل: استثناء الـ ID الحالي من فحص التكرار
            'code' => [
                'required',
                'string',
                'max:50',
                'unique:salary_rules,code,' . $ruleId
            ],

            'category' => ['required', new Enum(SalaryRuleCategory::class)],
            'type' => ['required', new Enum(SalaryRuleType::class)],
            'value' => ['nullable', 'numeric', 'min:0'],

            'percentage_of_code' => [
                'nullable',
                'string',
                'required_if:type,' . SalaryRuleType::Percentage->value
            ],

            'formula_expression' => [
                'nullable',
                'string',
                'required_if:type,' . SalaryRuleType::Formula->value
            ],

            'account_mapping_key' => ['nullable', 'string', 'exists:account_mappings,key'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string'],
        ];
    }
}
