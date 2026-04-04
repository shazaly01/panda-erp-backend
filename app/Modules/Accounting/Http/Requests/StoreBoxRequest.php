<?php

namespace App\Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Modules\Accounting\Models\Box;

class StoreBoxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Box::class);
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:100'],
            'currency_id' => ['required', 'exists:currencies,id'], // العملة ضرورية للإنشاء
            'branch_id'   => ['nullable', 'integer'], // إذا كان لديك جدول فروع: exists:branches,id
            'account_id'  => ['nullable', 'integer', 'exists:accounts,id'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active'   => ['boolean'],
        ];
    }
}
