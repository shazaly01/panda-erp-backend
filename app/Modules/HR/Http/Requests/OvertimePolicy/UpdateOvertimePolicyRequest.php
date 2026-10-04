<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\OvertimePolicy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOvertimePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $policyId = $this->route('overtime_policy')?->id ?? $this->route('overtime_policy');

        return [
            'name'                        => ['required', 'string', 'max:255', Rule::unique('hr_overtime_policies')->ignore($policyId)],
            'overtime_source'             => ['required', 'string', 'in:punch,supervisor'],
            'working_days_per_month'      => ['required', 'integer', 'min:1', 'max:31'],
            'working_hours_per_day'       => ['required', 'integer', 'min:1', 'max:24'],
            'regular_rate'                => ['required', 'numeric', 'min:1'],
            'weekend_rate'                => ['required', 'numeric', 'min:1'],
            'holiday_rate'                => ['required', 'numeric', 'min:1'],
            'is_daily_basis'              => ['required', 'boolean'],
            'hours_to_day_threshold'      => ['required_if:is_daily_basis,true', 'nullable', 'integer', 'min:1', 'max:24'],
            'wage_factor'                 => ['nullable', 'numeric', 'min:0.01', 'max:2.00'],
            'fixed_monthly_hours'         => ['nullable', 'integer', 'min:1', 'max:744'],
            'tax_rate'                    => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_exempt_employment_types'   => ['nullable', 'array'],
            'tax_exempt_employment_types.*' => ['string', 'max:50'],
        ];
    }
}