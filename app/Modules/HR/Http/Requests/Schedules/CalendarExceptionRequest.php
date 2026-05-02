<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\Schedules;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalendarExceptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // التفويض يتم عبر الـ Policy في الـ Controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            // الأنواع المتاحة: عطلة رسمية، حالة طوارئ، أو أخرى
            'type' => ['required', 'string', Rule::in(['holiday', 'emergency', 'other'])],
            'start_date' => ['required', 'date'],
            // تاريخ النهاية اختياري (للطوارئ الممتدة)، ولكن إن وجد يجب أن يكون بعد تاريخ البداية
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            // تحديد ما إذا كان العمل في هذه الفترة سيُحسب كإضافي
            'treat_as_overtime_if_worked' => ['required', 'boolean'],
        ];

        // التحقق من عدم تكرار الاسم لتجنب إدخال نفس العطلة مرتين (مع استثناء الحالي في حالة التعديل)
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $exceptionId = $this->route('calendar_exception');
            $rules['name'][] = Rule::unique('hr_calendar_exceptions', 'name')->ignore($exceptionId);
        } else {
            $rules['name'][] = Rule::unique('hr_calendar_exceptions', 'name');
        }

        return $rules;
    }

    /**
     * Custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'تاريخ النهاية يجب أن يكون مساوياً أو بعد تاريخ البداية.',
            'name.unique' => 'يوجد استثناء تقويمي أو عطلة مسجلة مسبقاً بهذا الاسم.',
        ];
    }
}
