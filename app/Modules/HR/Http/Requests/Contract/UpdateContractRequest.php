<?php
namespace App\Modules\HR\Http\Requests\Contract;
use Illuminate\Foundation\Http\FormRequest;
use App\Modules\HR\Enums\SalaryFrequency;
use Illuminate\Validation\Rules\Enum;

class UpdateContractRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // لا نسمح بتغيير الموظف في العقد بعد إنشائه
            'salary_structure_id' => ['required', 'exists:salary_structures,id'],
            'salary_frequency' => ['required', new Enum(SalaryFrequency::class)],
            'overtime_policy_id'  => ['nullable', 'exists:hr_overtime_policies,id'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'is_active' => ['boolean'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,png', 'max:2048'],
        ];
    }
}
