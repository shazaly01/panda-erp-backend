<?php

namespace App\Modules\HR\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class PostPayrollBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        // التحقق من صلاحية "الاعتماد" (عادة تكون للمدراء فقط)
        return $this->user()->hasPermissionTo('hr.payroll.post');
    }

    public function rules(): array
    {
        return [
            // مطلوب قائمة من الموظفين (مصفوفة)
            'employee_ids' => ['required', 'array', 'min:1'],

            // التأكد من أن كل رقم موظف موجود فعلاً في الجدول
            'employee_ids.*' => ['integer', 'exists:employees,id'],

            // تاريخ الاستحقاق (مهم جداً لتوجيه القيد للفترة المالية الصحيحة)
            'date' => ['required', 'date'],

            // شرح القيد (يظهر في دفتر الأستاذ)
            'description' => ['required', 'string', 'max:255'],
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
