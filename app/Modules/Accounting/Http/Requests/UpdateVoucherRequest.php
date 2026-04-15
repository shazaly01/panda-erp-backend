<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'exists:cost_centers,id'],
            'date' => ['required', 'date'],
            'payee_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],

            'box_id' => ['nullable', 'exists:boxes,id'],
            'bank_account_id' => ['nullable', 'exists:bank_accounts,id'],

            'currency_id' => ['required', 'exists:currencies,id'],
            'exchange_rate' => ['required', 'numeric', 'min:0.0001'],
            'amount' => ['required', 'numeric', 'min:0.01'],

            'details' => ['required', 'array', 'min:1'],
            'details.*.account_id' => ['required', 'exists:accounts,id'],
            'details.*.cost_center_id' => ['nullable', 'exists:cost_centers,id'],
            'details.*.amount' => ['required', 'numeric', 'min:0.01'],
            'details.*.description' => ['nullable', 'string', 'max:255'],

            // 👇 الإضافات الجديدة هنا لربط الأطراف (الموظفين) عند التعديل
            'details.*.party_type' => ['nullable', 'string', 'max:255'],
            'details.*.party_id' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            // 1. وسيلة الدفع
            $box = $this->input('box_id');
            $bank = $this->input('bank_account_id');

            if (!$box && !$bank) {
                $validator->errors()->add('payment_method', 'يجب تحديد وسيلة الدفع (خزينة أو حساب بنكي).');
            }
            if ($box && $bank) {
                $validator->errors()->add('payment_method', 'لا يمكن اختيار خزينة وحساب بنكي في نفس الوقت.');
            }

            // 2. مطابقة المجموع
            $totalAmount = (float) $this->input('amount');
            $detailsSum = collect($this->input('details'))->sum('amount');

            if (abs($totalAmount - $detailsSum) > 0.001) {
                $validator->errors()->add('amount', "مجموع مبالغ التفاصيل ({$detailsSum}) لا يساوي إجمالي السند ({$totalAmount}).");
            }
        });
    }

    // 👇 إضافة هذه الدالة لتجميل رسائل الخطأ
    public function attributes(): array
    {
        return [
            'branch_id' => 'الفرع',
            'box_id' => 'الخزينة',
            'bank_account_id' => 'الحساب البنكي',
            'payee_name' => 'اسم المستفيد / الدافع',
            'details' => 'تفاصيل السند',
            'details.*.account_id' => 'الحساب في السطر',
            'details.*.party_type' => 'نوع الطرف المستفيد',
            'details.*.party_id' => 'رقم الطرف المستفيد',
        ];
    }
}
