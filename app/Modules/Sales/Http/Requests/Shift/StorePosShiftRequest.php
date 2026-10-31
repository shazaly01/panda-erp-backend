<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Requests\Shift;

use Illuminate\Foundation\Http\FormRequest;

class StorePosShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'opening_cash' => ['required', 'numeric', 'min:0'],
            'warehouse_id' => ['nullable', 'integer', 'exists:inventory_warehouses,id'],
            'branch_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'opening_cash.required' => 'مبلغ العهدة الافتتاحية مطلوب.',
            'opening_cash.numeric' => 'يجب أن يكون مبلغ العهدة الافتتاحية رقماً صحيحاً.',
            'opening_cash.min' => 'لا يمكن أن تكون العهدة الافتتاحية بالسالب.',
            'warehouse_id.exists' => 'المستودع المحدد غير موجود بالنظام.',
        ];
    }
}