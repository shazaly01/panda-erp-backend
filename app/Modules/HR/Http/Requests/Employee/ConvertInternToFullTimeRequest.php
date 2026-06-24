<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class ConvertInternToFullTimeRequest extends FormRequest
{
    /**
     * التحقق من امتلاك صلاحية تحويل المتدرب لموظف دائم
     */
    public function authorize(): bool
    {
        return $this->user()->hasPermissionTo('hr.employees.convert');
    }

    /**
     * القوانين الصارمة لإلزام الـ HR بمدخلات العقد الرسمي الجديد للتعيين
     */
    public function rules(): array
    {
        return [
            'position_id' => ['required', 'exists:positions,id'],
            'department_id' => ['required', 'exists:departments,id'], // التثبيت الرسمي يستلزم قسماً محدداً قانونياً
            'working_schedule_id' => ['required', 'exists:hr_working_schedules,id'],
            'salary_structure_id' => ['required', 'exists:salary_structures,id'],
            'overtime_policy_id' => ['nullable', 'exists:hr_overtime_policies,id'],
            'pay_group_id' => ['required', 'exists:hr_pay_groups,id'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'manager_id' => ['nullable', 'exists:employees,id'],
        ];
    }

    /**
     * رسائل الخطأ المخصصة لعملية التثبيت
     */
    public function messages(): array
    {
        return [
            'position_id.required' => 'المسمى الوظيفي الجديد مطلوب لإتمام عملية التثبيت.',
            'position_id.exists' => 'المسمى الوظيفي المحدد غير موجود.',
            'department_id.required' => 'تحديد القسم الرسمي إلزامي عند تعيين موظف دائم.',
            'department_id.exists' => 'القسم المحدد غير موجود.',
            'working_schedule_id.required' => 'جدول العمل والورديات للموظف الدائم مطلوب.',
            'salary_structure_id.required' => 'هيكل الرواتب والأجور (التأمينات والضرائب) مطلوب للتعيين الرسمي.',
            'pay_group_id.required' => 'فئة الدفع المالي للموظف مطلوبة.',
            'basic_salary.required' => 'الراتب الأساسي الجديد للموظف مطلوب.',
            'basic_salary.numeric' => 'الراتب يجب أن يكون قيمة رقمية.',
        ];
    }
}
