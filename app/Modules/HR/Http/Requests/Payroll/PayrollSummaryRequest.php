<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\Payroll;

use App\Modules\HR\Policies\PayrollPolicy;
use Illuminate\Foundation\Http\FormRequest;

class PayrollSummaryRequest extends FormRequest
{
    /**
     * التحقق من صلاحية المستخدم لعرض ملخص مسير الرواتب
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('view', PayrollPolicy::class);
    }

    /**
     * قواعد التحقق الخاصة بحساب ملخص المسير
     */
    public function rules(): array
    {
        return [
            'batch_id'       => ['nullable', 'integer', 'exists:payroll_batches,id'],
            'employee_ids'   => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
            'pay_period_id'  => ['required', 'integer', 'exists:hr_pay_periods,id'],
            'run_type'       => ['required', 'string', 'in:regular,overtime_only'],
        ];
    }

    /**
     * رسائل الخطأ باللغة العربية
     */
    public function messages(): array
    {
        return [
            'batch_id.integer'        => 'معرف المسير يجب أن يكون رقماً صحيحاً.',
            'batch_id.exists'         => 'مسير الرواتب المحدد غير موجود في النظام.',
            'employee_ids.required'   => 'يجب اختيار موظف واحد على الأقل لحساب الملخص.',
            'employee_ids.array'      => 'قائمة الموظفين المرسلة غير صالحة.',
            'employee_ids.min'        => 'يجب اختيار موظف واحد على الأقل.',
            'employee_ids.*.integer'  => 'معرف الموظف يجب أن يكون رقماً صحيحاً.',
            'employee_ids.*.exists'   => 'أحد الموظفين المحددين غير موجود في النظام.',
            'pay_period_id.required'  => 'يجب تحديد الفترة المالية.',
            'pay_period_id.integer'   => 'معرف الفترة المالية يجب أن يكون رقماً صحيحاً.',
            'pay_period_id.exists'    => 'الفترة المالية المحددة غير موجودة في النظام.',
            'run_type.required'       => 'يجب تحديد نوع المسير (اعتيادي أو إضافي).',
            'run_type.in'             => 'نوع المسير غير صالح. الخيارات المتاحة: regular أو overtime_only.',
        ];
    }
}