<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePayrollInputRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'   => ['sometimes', 'required', 'in:bonus,penalty,allowance,deduction'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:1'],
            'date'   => ['sometimes', 'required', 'date'],
            'reason' => ['sometimes', 'required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in'    => 'نوع الحركة غير صحيح. الأنواع المسموحة هي: حافز، جزاء، بدل، استقطاع.',
            'amount.min' => 'يجب أن يكون المبلغ أكبر من صفر.',
        ];
    }
}
