<?php

namespace App\Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        // اسمح للجميع حالياً، أو قيدها بصلاحية 'accounting.settings.manage'
        return true;
    }

   public function rules(): array
{
    return [
        'account_id' => [
            'required',
            'integer',
            'exists:accounts,id',
            // قاعدة مخصصة لمنع اختيار الحسابات الرئيسية
            function ($attribute, $value, $fail) {
                $account = \App\Modules\Accounting\Models\Account::find($value);
                if ($account && !$account->is_transactional) { // أو check if it has children
                    $fail('لا يمكن التوجيه لحساب رئيسي، الرجاء اختيار حساب فرعي يقبل الحركات.');
                }
            }
        ],
    ];
}
}
