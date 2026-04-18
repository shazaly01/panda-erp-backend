<?php

namespace App\Modules\HR\Http\Requests\PayPeriod;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePayPeriodRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // لا نفضل تغيير مجموعة الدفع للفترة بعد إنشائها لارتباطها بالمسيرات
            'name'         => ['sometimes', 'required', 'string', 'max:255'],
            'start_date'   => ['sometimes', 'required', 'date'],
            'end_date'     => ['sometimes', 'required', 'date', 'after_or_equal:start_date'],
            'status'       => ['sometimes', 'required', 'string', 'in:open,processing,closed'],
        ];
    }
}
