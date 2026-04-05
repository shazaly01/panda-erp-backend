<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class StorePayrollInputRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],

            // حصر الأنواع المسموحة فقط لتجنب أي إدخال عشوائي يكسر مسير الرواتب
            'type' => ['required', 'in:bonus,penalty,allowance,deduction'],

            // المبلغ يجب أن يكون رقمياً وأكبر من صفر (حتى الخصم يُكتب كرقم موجب، والنظام يطرحه)
            'amount' => ['required', 'numeric', 'min:1'],

            'date' => ['required', 'date'],

            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'نوع الحركة غير صحيح. الأنواع المسموحة هي: حافز، جزاء، بدل، استقطاع.',
        ];
    }
}
