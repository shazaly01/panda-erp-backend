<?php

namespace App\Modules\HR\Http\Requests\PayPeriod;

use Illuminate\Foundation\Http\FormRequest;

class GeneratePayPeriodsRequest extends FormRequest
{
    public function authorize(): bool { return true; } // التفويض يتم في الـ Controller

    public function rules(): array
    {
        return [
            'pay_group_id' => ['required', 'exists:hr_pay_groups,id'],
            'year'         => ['required', 'integer', 'min:2020', 'max:2050'],
        ];
    }
}
