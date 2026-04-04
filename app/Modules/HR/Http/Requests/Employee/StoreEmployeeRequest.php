<?php

namespace App\Modules\HR\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Modules\HR\Enums\Gender;
use App\Modules\HR\Enums\MaritalStatus;
use App\Modules\HR\Enums\EmploymentType;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Modules\HR\Models\Employee::class);
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:200'],
            'national_id' => ['nullable', 'numeric', 'unique:employees,national_id'],
            'email' => ['nullable', 'email', 'unique:employees,email'],
            'phone' => ['nullable', 'string', 'max:20'],

            'employee_number' => ['required', 'string', 'unique:employees,employee_number'],
            'join_date' => ['required', 'date'],

            'gender' => ['nullable', Rule::enum(Gender::class)],
            'marital_status' => ['nullable', Rule::enum(MaritalStatus::class)],
            'employment_type' => ['required', Rule::enum(EmploymentType::class)],

            'department_id' => ['nullable', 'exists:departments,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'manager_id' => ['nullable', 'exists:employees,id'],
            'user_id' => ['nullable', 'exists:users,id'],
        ];
    }
}
