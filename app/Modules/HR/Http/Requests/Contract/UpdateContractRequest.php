<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\Contract;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContractRequest extends FormRequest
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
     * قواعد التحقق المطبقة على طلب التعديل.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            // لا نسمح بتغيير الموظف في العقد بعد إنشائه
            'salary_structure_id' => ['required', 'exists:salary_structures,id'],
            'overtime_policy_id'  => ['nullable', 'exists:hr_overtime_policies,id'],
            'pay_group_id'        => ['required', 'exists:hr_pay_groups,id'],
            'working_schedule_id' => ['required', 'exists:hr_working_schedules,id'],

            // استقبال وفحص نمط تسجيل الحضور عند التعديل لضمان عدم تصفيره أو تراجعه إلى الوضع اليدوي
            'attendance_mode'     => ['required', 'string', 'in:manual,auto'],

            'basic_salary'        => ['required', 'numeric', 'min:0'],
            'start_date'          => ['required', 'date'],
            'end_date'            => ['nullable', 'date', 'after:start_date'],
            'is_active'           => ['boolean'],
            'attachment'          => ['nullable', 'file', 'mimes:pdf,jpg,png', 'max:2048'],
        ];
    }
}
