<?php

namespace App\Modules\HR\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class PreviewPayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        // نرجع true لأننا نتعامل مع الصلاحيات بشكل أعمق داخل الـ Controller
        return true;
    }

   public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'start_date'  => ['required', 'date'], // تغيير من month إلى تاريخ بداية
            'end_date'    => ['required', 'date', 'after_or_equal:start_date'], // إضافة تاريخ نهاية
            'inputs'      => ['nullable', 'array'],
            'inputs.*'    => ['numeric', 'min:0'],
        ];
    }
}
