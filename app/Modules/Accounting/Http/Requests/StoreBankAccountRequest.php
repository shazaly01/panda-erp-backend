<?php

namespace App\Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Modules\Accounting\Models\BankAccount;

class StoreBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', BankAccount::class);
    }

    public function rules(): array
    {
        return [
            'bank_name'      => ['required', 'string', 'max:100'], // بنك الراجحي
            'account_name'   => ['required', 'string', 'max:100'], // الوصف: جاري الفرع الرئيسي
            'account_number' => ['required', 'string', 'max:50'],  // رقم الحساب الداخلي
            'iban'           => ['nullable', 'string', 'max:50'],  // يفضل إضافة regex للتحقق من الآيبان مستقبلاً
            'currency_id'    => ['required', 'exists:currencies,id'],
            'branch_id'      => ['nullable', 'integer'],
            'account_id'  => ['nullable', 'integer', 'exists:accounts,id'],
            'is_active'      => ['boolean'],
        ];
    }
}
