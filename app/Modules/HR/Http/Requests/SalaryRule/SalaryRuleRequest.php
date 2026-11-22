<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\SalaryRule;

use App\Modules\HR\Enums\SalaryRuleCategory;
use App\Modules\HR\Enums\SalaryRuleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class SalaryRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        // التحقق يتم في الـ Policy المستدعى من المتحكم
        return true;
    }

    /**
     * تنظيف الحقل الدائن تلقائياً إذا لم تكن الفئة مساهمة شركة
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('category') !== SalaryRuleCategory::CompanyContribution->value) {
            $this->merge([
                'credit_account_mapping_key' => null,
            ]);
        }
    }

    public function rules(): array
    {
        $rule = $this->route('salary_rule');
        $ruleId = is_object($rule) ? $rule->id : $rule;
        $companyContributionValue = SalaryRuleCategory::CompanyContribution->value;

        return [
            'name' => ['required', 'string', 'max:255'],

            'code' => [
                'required',
                'string',
                'max:50',
                'unique:salary_rules,code,' . $ruleId,
            ],

            'category' => ['required', new Enum(SalaryRuleCategory::class)],
            'type'     => ['required', new Enum(SalaryRuleType::class)],
            'value'    => ['nullable', 'numeric', 'min:0'],

            'percentage_of_code' => [
                'nullable',
                'string',
                'required_if:type,' . SalaryRuleType::Percentage->value,
            ],

            'formula_expression' => [
                'nullable',
                'string',
                'required_if:type,' . SalaryRuleType::Formula->value,
            ],

            'account_mapping_key' => [
                'nullable',
                'string',
                'exists:account_mappings,key',
                'required_if:category,' . $companyContributionValue,
            ],

            'credit_account_mapping_key' => [
                'nullable',
                'string',
                'exists:account_mappings,key',
                'required_if:category,' . $companyContributionValue,
                'different:account_mapping_key',
            ],

            'is_active'   => ['boolean'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'account_mapping_key.required_if'        => 'يجب تحديد حساب المصروف (المدين) عند اختيار فئة مساهمة شركة.',
            'credit_account_mapping_key.required_if' => 'يجب تحديد حساب الالتزام/الهيئة (الدائن) عند اختيار فئة مساهمة شركة.',
            'credit_account_mapping_key.exists'      => 'مفتاح التوجيه المحاسبي للطرف الدائن غير موجود.',
            'credit_account_mapping_key.different'   => 'لا يمكن أن يكون حساب المصروف (المدين) وحساب الالتزام (الدائن) متطابقين.',
        ];
    }
}