<?php
namespace App\Modules\HR\Http\Requests\Contract;
use Illuminate\Foundation\Http\FormRequest;
use App\Modules\HR\Enums\SalaryFrequency;
use Illuminate\Validation\Rules\Enum;
class StoreContractRequest extends FormRequest
{
    public function authorize(): bool { return true; } // التفويض للـ Policy

   public function rules(): array
    {
        return [
            'employee_id'         => ['required', 'exists:employees,id'],
            'salary_structure_id' => ['required', 'exists:salary_structures,id'],
            'overtime_policy_id'  => ['nullable', 'exists:hr_overtime_policies,id'], // 👈 تمت الإضافة هنا
            'salary_frequency'    => ['required', new Enum(SalaryFrequency::class)],
            'basic_salary'        => ['required', 'numeric', 'min:0'],
            'start_date'          => ['required', 'date'],
            'end_date'            => ['nullable', 'date', 'after:start_date'],
            'attachment'          => ['nullable', 'file', 'mimes:pdf,jpg,png', 'max:2048'],
        ];
    }
}
