<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalesInvoicePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sales_invoice_id' => ['required', 'integer', 'exists:sales_invoices,id'],
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
            'sales_invoice_id.required' => 'معرف فاتورة المبيعات مطلوب.',
            'sales_invoice_id.exists' => 'فاتورة المبيعات المحددة غير موجودة بالنظام.',
            'payment_method.required' => 'طريقة الدفع مطلوبة.',
            'payment_method.in' => 'طريقة الدفع المحددة غير صالحة.',
            'amount.required' => 'مبلغ السداد مطلوب.',
            'amount.gt' => 'يجب أن يكون مبلغ السداد أكبر من الصفر.',
            'box_id.exists' => 'الخزينة المحددة غير موجودة بالنظام.',
            'bank_account_id.exists' => 'الحساب البنكي المحدد غير موجود بالنظام.',
            'shift_id.exists' => 'وردية نقطة البيع المحددة غير موجودة.',
        ];
    }
}