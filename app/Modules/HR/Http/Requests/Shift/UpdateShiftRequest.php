<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\Shift;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $shiftId = $this->route('shift')->id ?? $this->route('shift');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('hr_shifts', 'name')->ignore($shiftId)],
            'start_time' => ['sometimes', 'required', 'date_format:H:i'],
            'end_time' => ['sometimes', 'required', 'date_format:H:i'],
            'grace_period_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            'is_active' => ['boolean'],
        ];
    }
}
