<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\Loan;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'               => ['sometimes', 'required', 'numeric', 'min:1'],
            'reason'               => ['sometimes', 'required', 'string', 'max:255'],
            'deduction_start_date' => ['sometimes', 'required', 'date'],
            'installments_count'   => ['sometimes', 'required', 'integer', 'min:1', 'max:60'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min'             => 'مبلغ السلفة يجب أن يكون أكبر من صفر.',
            'installments_count.max' => 'عدد الأقساط لا يمكن أن يتجاوز 60 شهراً.',
        ];
    }
}
