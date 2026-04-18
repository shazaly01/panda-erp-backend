<?php

namespace App\Modules\HR\Http\Requests\PayPeriod;

use Illuminate\Foundation\Http\FormRequest;

class StorePayPeriodRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'pay_group_id' => ['required', 'exists:hr_pay_groups,id'],
            'name'         => ['required', 'string', 'max:255'], // مثال: فترة أبريل 2026
            'start_date'   => ['required', 'date'],
            'end_date'     => ['required', 'date', 'after_or_equal:start_date'],
            'status'       => ['nullable', 'string', 'in:open,processing,closed'],
        ];
    }
}
