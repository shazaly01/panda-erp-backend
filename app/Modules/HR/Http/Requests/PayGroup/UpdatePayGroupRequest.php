<?php

namespace App\Modules\HR\Http\Requests\PayGroup;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use App\Modules\HR\Enums\SalaryFrequency;

class UpdatePayGroupRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'      => ['sometimes', 'required', 'string', 'max:255'],
            'frequency' => ['sometimes', 'required', new Enum(SalaryFrequency::class)],
            'is_active' => ['boolean'],
        ];
    }
}
