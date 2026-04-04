<?php

namespace App\Modules\HR\Http\Requests\Position;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('position'));
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('positions')->ignore($this->position)],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }
}
