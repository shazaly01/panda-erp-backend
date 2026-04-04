<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Enums\AccountNature;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        // نمرر الحساب الحالي الموجود في الرابط للـ Policy
        return $this->user()->can('update', $this->route('account'));
    }

    public function rules(): array
    {
        // نحصل على الحساب الحالي من الرابط لاستثناء الـ ID
        $account = $this->route('account');

        return [
            // استثناء الـ ID الحالي من فحص التكرار
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('accounts', 'code')->ignore($account->id)],

            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'nature' => ['sometimes', 'required', Rule::enum(AccountNature::class)],
            'type' => ['sometimes', 'required', 'string', 'max:50'],

            // التحقق من أن الأب ليس هو نفس الحساب (لمنع الحلقات اللانهائية)
            'parent_id' => ['nullable', 'exists:accounts,id', Rule::notIn([$account->id])],

            'currency_id' => ['nullable', 'integer'],
            'is_transactional' => ['boolean'],
            'requires_cost_center' => ['boolean'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
