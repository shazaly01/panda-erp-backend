<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\Schedules;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShiftOverrideRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // التفويض يتم عبر الـ Policy
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'employee_id' => ['required', 'integer', Rule::exists('hr_employees', 'id')->whereNull('deleted_at')],
            'date' => ['required', 'date'],
            'original_shift_id' => ['nullable', 'integer', Rule::exists('hr_shifts', 'id')->whereNull('deleted_at')],
            'new_shift_id' => ['nullable', 'integer', Rule::exists('hr_shifts', 'id')->whereNull('deleted_at')],
            'reason' => ['nullable', 'string', 'max:255'],
        ];

        // التحقق المعقد من عدم تكرار التجاوز لنفس الموظف في نفس اليوم
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $overrideId = $this->route('shift_override');
            $rules['date'][] = Rule::unique('hr_shift_overrides')->where(function ($query) {
                return $query->where('employee_id', $this->input('employee_id'));
            })->ignore($overrideId);
        } else {
            $rules['date'][] = Rule::unique('hr_shift_overrides')->where(function ($query) {
                return $query->where('employee_id', $this->input('employee_id'));
            });
        }

        return $rules;
    }

    /**
     * Custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'date.unique' => 'يوجد تجاوز أو تبديل وردية مسجل مسبقاً لهذا الموظف في نفس هذا اليوم.',
            'employee_id.exists' => 'الموظف المحدد غير موجود في النظام.',
        ];
    }
}
