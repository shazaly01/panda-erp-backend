<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\Payroll;

use App\Modules\HR\Policies\PayrollPolicy;
use Illuminate\Foundation\Http\FormRequest;

class PreviewPayrollRequest extends FormRequest
{
    /**
     * التحقق من صلاحية المستخدم لمعاينة قسيمة الراتب
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('preview', PayrollPolicy::class);
    }

    /**
     * قواعد التحقق الخاصة بمعاينة قسيمة الراتب
     */
    public function rules(): array
    {
        return [
            'employee_id'   => ['required', 'integer', 'exists:employees,id'],
            'pay_period_id' => ['required', 'integer', 'exists:hr_pay_periods,id'],
            'run_type'      => ['required', 'string', 'in:regular,overtime_only'],
        ];
    }

    /**
     * رسائل الخطأ باللغة العربية
     */
    public function messages(): array
    {
        return [
            'employee_id.required'   => 'يجب تحديد الموظف المراد معاينة راتبه.',
            'employee_id.integer'    => 'معرف الموظف يجب أن يكون رقماً صحيحاً.',
            'employee_id.exists'     => 'الموظف المحدد غير موجود في النظام.',
            'pay_period_id.required' => 'يجب تحديد الفترة المالية.',
            'pay_period_id.integer'  => 'معرف الفترة المالية يجب أن يكون رقماً صحيحاً.',
            'pay_period_id.exists'   => 'الفترة المالية المحددة غير موجودة في النظام.',
            'run_type.required'      => 'يجب تحديد نوع المسير (اعتيادي أو إضافي).',
            'run_type.in'            => 'نوع المسير غير صالح. الخيارات المتاحة: regular أو overtime_only.',
        ];
    }
}