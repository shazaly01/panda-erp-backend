<?php

namespace App\Modules\HR\Http\Requests\Position;

use Illuminate\Foundation\Http\FormRequest;

class StorePositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Modules\HR\Models\Position::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:50', 'unique:positions,code'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }
}
