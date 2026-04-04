<?php

namespace App\Modules\HR\Http\Requests\SalaryRule; // <--- تعديل الـ Namespace

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use App\Modules\HR\Enums\SalaryRuleCategory;
use App\Modules\HR\Enums\SalaryRuleType;

class SalaryRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermissionTo('hr.settings.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // تعديل التحقق من الكود ليأخذ الـ ID من المسار بشكل صحيح
            'code' => ['required', 'string', 'max:50', 'unique:salary_rules,code,' . $this->route('salary_rule')],

            'category' => ['required', new Enum(SalaryRuleCategory::class)],
            'type' => ['required', new Enum(SalaryRuleType::class)],
            'value' => ['nullable', 'numeric', 'min:0'],
            'percentage_of_code' => ['nullable', 'string', 'required_if:type,' . SalaryRuleType::Percentage->value],
            'formula_expression' => ['nullable', 'string', 'required_if:type,' . SalaryRuleType::Formula->value],
            'account_mapping_key' => ['nullable', 'string', 'exists:account_mappings,key'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string'],
        ];
    }
}
