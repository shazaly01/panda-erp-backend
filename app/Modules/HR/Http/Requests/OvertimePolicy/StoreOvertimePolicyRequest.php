<?php

namespace App\Modules\HR\Http\Requests\OvertimePolicy;

use Illuminate\Foundation\Http\FormRequest;

class StoreOvertimePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // الصلاحيات تتم عبر الـ Controller & Policy
    }

    public function rules(): array
    {
        return [
            'name'                   => ['required', 'string', 'max:255', 'unique:hr_overtime_policies,name'],
            'working_days_per_month' => ['required', 'integer', 'min:1', 'max:31'],
            'working_hours_per_day'  => ['required', 'integer', 'min:1', 'max:24'],
            'regular_rate'           => ['required', 'numeric', 'min:1'],
            'weekend_rate'           => ['required', 'numeric', 'min:1'],
            'holiday_rate'           => ['required', 'numeric', 'min:1'],
            'is_daily_basis'         => ['required', 'boolean'],
            'hours_to_day_threshold' => ['required_if:is_daily_basis,true', 'integer', 'min:1', 'max:24'],
        ];
    }
}
