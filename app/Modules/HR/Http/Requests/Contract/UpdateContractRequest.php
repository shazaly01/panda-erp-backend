<?php
namespace App\Modules\HR\Http\Requests\Contract;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContractRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // لا نسمح بتغيير الموظف في العقد بعد إنشائه
            'salary_structure_id' => ['required', 'exists:salary_structures,id'],
            'overtime_policy_id'  => ['nullable', 'exists:hr_overtime_policies,id'],
            'pay_group_id'        => ['required', 'exists:hr_pay_groups,id'], // 👈 الإضافة الجديدة (مجموعة الدفع)
            'basic_salary'        => ['required', 'numeric', 'min:0'],
            'start_date'          => ['required', 'date'],
            'end_date'            => ['nullable', 'date', 'after:start_date'],
            'is_active'           => ['boolean'],
            'attachment'          => ['nullable', 'file', 'mimes:pdf,jpg,png', 'max:2048'],
        ];
    }
}
