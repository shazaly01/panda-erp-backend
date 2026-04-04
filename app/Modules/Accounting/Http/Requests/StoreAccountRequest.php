<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Enums\AccountNature;

class StoreAccountRequest extends FormRequest
{
    /**
     * هل يحق للمستخدم تنفيذ هذا الطلب؟
     */
    public function authorize(): bool
    {
        // نستدعي الـ Policy التي أنشأناها في الخطوة السابقة
        return $this->user()->can('create', Account::class);
    }

    /**
     * قواعد التحقق
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:accounts,code'],
            'name' => ['required', 'string', 'max:150'],

            // التحقق من القيم الثابتة باستخدام الـ Enum
            'nature' => ['required', Rule::enum(AccountNature::class)],

            // نوع الحساب (أصول، خصوم...) يمكن جعله Enum أيضاً أو نص حالياً
            'type' => ['required', 'string', 'max:50'],

            // التحقق من الأب (يجب أن يكون موجوداً)
            'parent_id' => ['nullable', 'exists:accounts,id'],

            'currency_id' => ['nullable', 'integer'], // سنضيف exists:currencies,id لاحقاً

            'is_transactional' => ['boolean'],
            'requires_cost_center' => ['boolean'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
