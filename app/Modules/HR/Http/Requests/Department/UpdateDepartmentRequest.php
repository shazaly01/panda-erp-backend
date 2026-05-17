<?php

namespace App\Modules\HR\Http\Requests\Department;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Modules\HR\Enums\DepartmentType;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('department'));
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('departments')->ignore($this->department)],
            'type' => ['sometimes', Rule::enum(DepartmentType::class)],
            'parent_id' => ['nullable', 'exists:departments,id'],
            'cost_center_id' => ['nullable', 'exists:cost_centers,id'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            // التحقق من مصفوفة المشرفين في التحديث
            'supervisor_ids' => ['nullable', 'array'],
            'supervisor_ids.*' => ['required', 'exists:employees,id'],
        ];
    }
}
