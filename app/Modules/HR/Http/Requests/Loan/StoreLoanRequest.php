<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\Loan;

use Illuminate\Foundation\Http\FormRequest;

class StoreLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],

            // يجب أن يكون المبلغ رقماً (numeric) لدعم الكسور (decimal)، وأكبر من صفر
            'amount' => ['required', 'numeric', 'min:1'],

            'reason' => ['required', 'string', 'max:255'],

            // تاريخ بدء الخصم (يجب أن يكون تاريخاً صحيحاً)
            'deduction_start_date' => ['required', 'date'],

            // عدد الأقساط (integer)، ولا يمكن أن يكون أقل من شهر، ونضع حداً أقصى منطقياً (مثلاً 60 شهراً كحد أقصى للسداد)
            'installments_count' => ['required', 'integer', 'min:1', 'max:60'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'مبلغ السلفة يجب أن يكون أكبر من صفر.',
            'installments_count.max' => 'عدد الأقساط لا يمكن أن يتجاوز 60 شهراً.',
        ];
    }
}
