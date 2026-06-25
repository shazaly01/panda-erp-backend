<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\Contract;

use Illuminate\Foundation\Http\FormRequest;

class StoreContractRequest extends FormRequest
{
    /**
     * تحديد ما إذا كان المستخدم مخولاً لإجراء هذا الطلب.
     * يتم التفويض الفعلي مركزيًا عبر الـ Policy الخاصة بالعقود.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * قواعد التحقق المطبقة على الطلب.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'employee_id'         => ['required', 'exists:employees,id'],
            'salary_structure_id' => ['required', 'exists:salary_structures,id'],
            'overtime_policy_id'  => ['nullable', 'exists:hr_overtime_policies,id'],
            'pay_group_id'        => ['required', 'exists:hr_pay_groups,id'],

            // ربط العقد إلزامياً بقالب جدولة عمل (Working Schedule)
            'working_schedule_id' => ['required', 'exists:hr_working_schedules,id'],

            // استقبال وفحص نمط تسجيل الحضور (يدوي أم تلقائي) لمنع إجبار الموديل له على الوضع اليدوي
            'attendance_mode'     => ['required', 'string', 'in:manual,auto'],

            'basic_salary'        => ['required', 'numeric', 'min:0'],
            'start_date'          => ['required', 'date'],
            'end_date'            => ['nullable', 'date', 'after:start_date'],
            'attachment'          => ['nullable', 'file', 'mimes:pdf,jpg,png', 'max:2048'],
        ];
    }
}
