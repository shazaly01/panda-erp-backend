<?php

namespace App\Modules\HR\Http\Requests\Department;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Modules\HR\Enums\DepartmentType;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Modules\HR\Models\Department::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:50', 'unique:departments,code'],
            'type' => ['required', Rule::enum(DepartmentType::class)],
            'parent_id' => ['nullable', 'exists:departments,id'],
            'cost_center_id' => ['nullable', 'exists:cost_centers,id'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }
}
