<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\Shift;

use Illuminate\Foundation\Http\FormRequest;

class StoreShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // الصلاحيات تدار عبر الـ Policy
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'unique:hr_shifts,name'],
            'start_time' => ['required', 'date_format:H:i'], // صيغة الوقت 08:00
            'end_time' => ['required', 'date_format:H:i'],
            'grace_period_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'اسم الوردية مسجل مسبقاً، يرجى اختيار اسم آخر.',
            'start_time.date_format' => 'صيغة وقت الحضور غير صحيحة، يجب أن تكون (ساعة:دقيقة).',
            'end_time.date_format' => 'صيغة وقت الانصراف غير صحيحة، يجب أن تكون (ساعة:دقيقة).',
        ];
    }
}
