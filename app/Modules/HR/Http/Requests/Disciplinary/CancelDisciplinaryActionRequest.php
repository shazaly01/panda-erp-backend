<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\Disciplinary;

use Illuminate\Foundation\Http\FormRequest;

class CancelDisciplinaryActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cancellation_reason' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'cancellation_reason.required' => 'يرجى توضيح سبب إلغاء الجزاء التأديبي.',
            'cancellation_reason.min'      => 'سبب الإلغاء يجب أن يحتوي على 3 أحرف على الأقل.',
            'cancellation_reason.max'      => 'سبب الإلغاء يجب ألا يتجاوز 255 حرفاً.',
        ];
    }
}