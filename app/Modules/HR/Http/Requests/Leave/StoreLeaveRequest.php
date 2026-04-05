<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\Leave;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveRequest extends FormRequest
{
    /**
     * تحديد الصلاحية (تمت معالجتها بالفعل في طبقة الـ Policies)
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * قواعد التحقق
     */
    public function rules(): array
    {
        return [
            // التحقق من وجود الموظف (في حال كان الـ HR هو من يدخل الطلب نيابة عنه)
            'employee_id' => ['required', 'exists:employees,id'],

            // التحقق من أن نوع الإجازة صحيح وموجود
            'leave_type_id' => ['required', 'exists:hr_leave_types,id'],

            // تواريخ الإجازة
            'start_date' => ['required', 'date'],

            // يجب أن يكون تاريخ النهاية مساوياً لتاريخ البداية (إجازة يوم واحد) أو بعده
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],

            // السبب اختياري، ولكن نضع له حداً أقصى لتجنب إدخال نصوص ضخمة
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * رسائل الخطأ المخصصة (اختياري لتوضيح الخطأ للمستخدم)
     */
    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'تاريخ نهاية الإجازة يجب أن يكون مساوياً أو بعد تاريخ البداية.',
        ];
    }
}
