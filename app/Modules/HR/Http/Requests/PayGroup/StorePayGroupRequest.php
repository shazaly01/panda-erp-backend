<?php

namespace App\Modules\HR\Http\Requests\PayGroup;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use App\Modules\HR\Enums\SalaryFrequency;

class StorePayGroupRequest extends FormRequest
{
    public function authorize(): bool { return true; } // التفويض يتم في الـ Controller عبر الـ Policy

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:255'],
            'frequency' => ['required', new Enum(SalaryFrequency::class)],
            'is_active' => ['boolean'],
        ];
    }
}
