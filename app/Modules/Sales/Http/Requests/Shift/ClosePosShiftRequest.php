<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Requests\Shift;

use Illuminate\Foundation\Http\FormRequest;

class ClosePosShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'actual_cash' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'actual_cash.required' => 'مبلغ النقد الفعلي بالدرج مطلوب عند الإغلاق.',
            'actual_cash.numeric' => 'يجب أن يكون المبلغ النقدي الفعلي رقماً.',
            'actual_cash.min' => 'لا يمكن أن يكون النقد الفعلي بالسالب.',
        ];
    }
}