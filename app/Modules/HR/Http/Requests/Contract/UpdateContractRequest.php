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
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'is_active' => ['boolean'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,png', 'max:2048'],
        ];
    }
}
