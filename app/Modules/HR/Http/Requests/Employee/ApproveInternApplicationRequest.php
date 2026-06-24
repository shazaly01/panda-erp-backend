<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class ApproveInternApplicationRequest extends FormRequest
{
    /**
     * التحقق من امتلاك الموظف لصلاحية الشاشة الموحدة لإتمام عملية الاعتماد والقبول
     */
    public function authorize(): bool
    {
        return $this->user()->hasPermissionTo('hr.internship_applications.view');
    }

    /**
     * قوانين التحقق لمدخلات القبول والاعتماد للمتدرب لضمان سلامة الهيكل الإداري
     */
    public function rules(): array
    {
        return [
            'department_id' => ['nullable', 'exists:departments,id'],
            'working_schedule_id' => ['required', 'exists:hr_working_schedules,id'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'manager_id' => ['nullable', 'exists:employees,id'],
        ];
    }

    /**
     * رسائل الخطأ المخصصة باللغة العربية لعرضها مباشرة في واجهة الـ Vue 3
     */
    public function messages(): array
    {
        return [
            'department_id.exists' => 'القسم المحدد غير موجود بالنظام.',
            'working_schedule_id.required' => 'يجب تحديد جدول العمل والوردية الخاصة بالمتدرب لضبط نظام الحضور والغياب.',
            'working_schedule_id.exists' => 'جدول العمل والوردية المحددة غير موجودة بسجلات النظام.',
            'basic_salary.required' => 'حقل المكافأة المالية المقطوعة مطلوب (أدخل 0 إذا كان التدريب تطوعياً/غير مدفوع).',
            'basic_salary.numeric' => 'المكافأة المالية يجب أن تكون قيمة رقمية صحيحة.',
            'manager_id.exists' => 'المشرف المباشر المحدد غير موجود بسجلات الموظفين حالياً.',
        ];
    }
}
