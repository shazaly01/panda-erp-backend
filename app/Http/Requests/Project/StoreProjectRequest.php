<?php

namespace App\Http\Requests\Project;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Project::class);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'project_owner' => 'nullable|string|max:255',
            'contract_value' => 'nullable|numeric|min:0',
            'due_value' => 'required|numeric|min:0',
            'award_date' => 'nullable|date',
            'company_id' => 'required|integer|exists:companies,id',
        ];
    }
}
