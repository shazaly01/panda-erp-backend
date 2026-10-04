<?php
declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\Payroll;

use App\Modules\HR\Policies\PayrollPolicy;
use Illuminate\Foundation\Http\FormRequest;

class GetEligibleEmployeesRequest extends FormRequest
{
    /**
     * التحقق من صلاحية المستخدم لعرض بيانات مسير الرواتب
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('view', PayrollPolicy::class);
    }

    /**
     * قواعد التحقق الخاصة بمعايير جلب الموظفين المؤهلين
     */
    public function rules(): array
    {
        return [
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
            'pay_period_id.required' => 'يجب تحديد الفترة المالية المراد معالجة رواتبها.',
            'pay_period_id.integer'  => 'معرف الفترة المالية يجب أن يكون رقماً صحيحاً.',
            'pay_period_id.exists'   => 'الفترة المالية المحددة غير موجودة في النظام.',
            'run_type.required'      => 'يجب تحديد نوع المسير (اعتيادي أو إضافي).',
            'run_type.in'            => 'نوع المسير غير صالح. الخيارات المتاحة: regular أو overtime_only.',
        ];
    }
}