<?php

namespace App\Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('bank_account'));
    }

    public function rules(): array
    {
        return [
            'bank_name'      => ['required', 'string', 'max:100'],
            'account_name'   => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'max:50'],
            'iban'           => ['nullable', 'string', 'max:50'],
            // أيضاً، تغيير العملة لحساب بنكي قائم هو عملية خطرة جداً، لذا لم أدرجها
            'branch_id'      => ['nullable', 'integer'],
            'account_id'  => ['nullable', 'integer', 'exists:accounts,id'],
            'is_active'      => ['boolean'],
        ];
    }
}
