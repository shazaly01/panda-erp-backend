<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\Payroll;

use App\Modules\HR\Policies\PayrollPolicy;
use Illuminate\Foundation\Http\FormRequest;

class PostPayrollBatchRequest extends FormRequest
{
    /**
     * التحقق من صلاحية المستخدم لاعتماد وترحيل مسير الرواتب
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('postBatch', PayrollPolicy::class);
    }

    /**
     * قواعد التحقق الخاصة باعتماد وترحيل المسير
     */
    public function rules(): array
    {
        return [
            'employee_ids'   => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
            'pay_period_id'  => ['required', 'integer', 'exists:hr_pay_periods,id'],
            'run_type'       => ['required', 'string', 'in:regular,overtime_only'],
            'description'    => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * رسائل الخطأ باللغة العربية
     */
    public function messages(): array
    {
        return [
            'employee_ids.required'   => 'يجب اختيار موظف واحد على الأقل لاعتماد وترحيل المسير.',
            'employee_ids.array'      => 'قائمة الموظفين المرسلة غير صالحة.',
            'employee_ids.min'        => 'يجب اختيار موظف واحد على الأقل.',
            'employee_ids.*.integer'  => 'معرف الموظف يجب أن يكون رقماً صحيحاً.',
            'employee_ids.*.exists'   => 'أحد الموظفين المحددين غير موجود في النظام.',
            'pay_period_id.required'  => 'يجب تحديد الفترة المالية المراد ترحيل رواتبها.',
            'pay_period_id.integer'   => 'معرف الفترة المالية يجب أن يكون رقماً صحيحاً.',
            'pay_period_id.exists'    => 'الفترة المالية المحددة غير موجودة في النظام.',
            'run_type.required'       => 'يجب تحديد نوع المسير (اعتيادي أو إضافي).',
            'run_type.in'             => 'نوع المسير غير صالح. الخيارات المتاحة: regular أو overtime_only.',
            'description.required'    => 'وصف أو بيان مسير الرواتب مطلوب لإنشاء القيد المحاسبي.',
            'description.max'         => 'وصف المسير يجب ألا يتجاوز 255 حرفاً.',
        ];
    }
}