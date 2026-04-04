<?php

namespace App\Modules\HR\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class PreviewPayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        // التحقق من الصلاحية الأمنية
        return $this->user()->hasPermissionTo('hr.payroll.view');
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'month' => ['nullable', 'date_format:Y-m'], // اختياري، قد نحتاجه لاحقاً لتحديد الشهر

            // المدخلات الخارجية المتغيرة (مثل السلف، الغياب)
            // نتوقع مصفوفة مثل: inputs[LOAN]=200, inputs[ABSENCE]=100
            'inputs' => ['nullable', 'array'],
            'inputs.*' => ['numeric', 'min:0'],
        ];
    }
}
