<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\Schedules;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkingScheduleRequest extends FormRequest
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
            'type' => ['required', 'string', Rule::in(['fixed', 'rotating'])],
            'cycle_days' => ['required', 'integer', 'min:1', 'max:365'],

            // مصفوفة الأيام (Lines)
            'lines' => ['required', 'array', 'size:' . request('cycle_days', 0)],
            'lines.*.day_number' => ['required', 'integer', 'min:1', 'max:' . request('cycle_days', 0)],
            // shift_id يمكن أن يكون null إذا كان اليوم يوم راحة (Off Day)
            'lines.*.shift_id' => ['nullable', 'integer', Rule::exists('hr_shifts', 'id')->whereNull('deleted_at')],
        ];

        // في حالة التحديث، نتجاهل الاسم الحالي من قاعدة الـ Unique
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $workingSchedule = $this->route('working_schedule');
            $rules['name'] = ['required', 'string', 'max:255', Rule::unique('hr_working_schedules', 'name')->ignore($workingSchedule)];
        } else {
            $rules['name'] = ['required', 'string', 'max:255', Rule::unique('hr_working_schedules', 'name')];
        }

        return $rules;
    }

    /**
     * Custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'lines.size' => 'يجب أن يتطابق عدد الأيام المرسلة مع طول الدورة (cycle_days).',
            'lines.*.shift_id.exists' => 'الوردية المحددة غير موجودة أو تم حذفها.',
        ];
    }
}
