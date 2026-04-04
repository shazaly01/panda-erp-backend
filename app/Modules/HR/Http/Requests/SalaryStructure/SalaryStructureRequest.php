<?php

namespace App\Modules\HR\Http\Requests\SalaryStructure;

use Illuminate\Foundation\Http\FormRequest;

class SalaryStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermissionTo('hr.settings.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],

            // التحقق من القواعد المرفقة مع الهيكل
            // نتوقع مصفوفة تحتوي على معرف القاعدة وترتيبها
            'rules' => ['nullable', 'array'],
            'rules.*.rule_id' => ['required', 'exists:salary_rules,id'],
            'rules.*.sequence' => ['required', 'integer', 'min:1'],
        ];
    }
}
