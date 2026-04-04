<?php

namespace App\Modules\HR\Http\Requests\Contract;

use Illuminate\Foundation\Http\FormRequest;

class StoreContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Modules\HR\Models\Contract::class);
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'salary_structure_id' => ['required', 'exists:salary_structures,id'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,png', 'max:2048'],
        ];
    }
}
