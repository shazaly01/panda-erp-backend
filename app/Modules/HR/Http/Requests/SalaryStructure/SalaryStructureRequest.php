<?php

namespace App\Modules\HR\Http\Requests\SalaryStructure; // <--- أضفنا Http هنا

use Illuminate\Foundation\Http\FormRequest;

class SalaryStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        // نرجع true لترك المسؤولية للـ Policy المرتبط بالمتحكم (Controller)
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],

            // التحقق من مصفوفة القواعد المرفقة
            'rules' => ['nullable', 'array'],
            'rules.*.rule_id' => ['required', 'exists:salary_rules,id'],

            // الترتيب ضروري تقنياً لربط البيانات في الجداول الوسيطة (Pivot)
            'rules.*.sequence' => ['required', 'integer', 'min:1'],
        ];
    }
}
