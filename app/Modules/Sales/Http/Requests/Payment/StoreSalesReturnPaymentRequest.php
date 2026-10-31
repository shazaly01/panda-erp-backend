<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalesReturnPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sales_return_id' => ['required', 'integer', 'exists:sales_returns,id'],
            'shift_id' => ['nullable', 'integer', 'exists:pos_shifts,id'],
            'payment_method' => ['required', 'string', 'in:cash,card,bank_transfer,on_account'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'box_id' => ['nullable', 'integer', 'exists:boxes,id'],
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'transaction_reference' => ['nullable', 'string', 'max:100'],
            'payment_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'sales_return_id.required' => 'معرف مرتجع المبيعات مطلوب.',
            'sales_return_id.exists' => 'مرتجع المبيعات المحدد غير موجود بالنظام.',
            'payment_method.required' => 'طريقة استرداد المبلغ مطلوبة.',
            'payment_method.in' => 'طريقة استرداد المبلغ غير صالحة.',
            'amount.required' => 'مبلغ الاسترداد مطلوب.',
            'amount.gt' => 'يجب أن يكون مبلغ الاسترداد أكبر من الصفر.',
            'box_id.exists' => 'الخزينة المحددة غير موجودة بالنظام.',
            'bank_account_id.exists' => 'الحساب البنكي المحدد غير موجود بالنظام.',
            'shift_id.exists' => 'وردية نقطة البيع المحددة غير موجودة.',
        ];
    }
}