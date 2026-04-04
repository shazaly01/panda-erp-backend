<?php

namespace App\Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBoxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('box'));
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:100'],
            // عادة لا نسمح بتغيير العملة لخزينة تم إنشاؤها وعليها حركات، لذا لم أضع currency_id هنا
            // إذا كنت تريد السماح بذلك بحذر، أضفه للقائمة.
            'branch_id'   => ['nullable', 'integer'],
            'account_id'  => ['nullable', 'integer', 'exists:accounts,id'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active'   => ['boolean'],
        ];
    }
}
