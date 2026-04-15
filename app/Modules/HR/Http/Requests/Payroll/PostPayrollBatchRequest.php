<?php

namespace App\Modules\HR\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class PostPayrollBatchRequest extends FormRequest
{
   public function authorize(): bool
    {
        // نرجع true لأننا نتعامل مع الصلاحيات بشكل أعمق داخل الـ Controller
        return true;
    }

   public function rules(): array
    {
        return [
            'employee_ids'   => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
            'start_date'     => ['required', 'date'], // إضافة تاريخ بداية المسير
            'end_date'       => ['required', 'date', 'after_or_equal:start_date'], // إضافة تاريخ نهاية المسير
            'description'    => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_ids.required' => 'يجب اختيار موظف واحد على الأقل للاعتماد.',
            'date.required' => 'تاريخ الاستحقاق مطلوب لإنشاء القيد.',
        ];
    }
}
