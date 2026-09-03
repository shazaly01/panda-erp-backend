<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests\Units;

use App\Modules\Inventory\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnitRequest extends FormRequest
{
    /**
     * تحديد ما إذا كان المستخدم مصرحاً له بتنفيذ هذا الطلب
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Unit::class);
    }

    /**
     * قواعد التحقق الخاصة بإنشاء وحدة قياس جديدة
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('inventory_units', 'name')->where(fn ($q) => $q->whereNull('deleted_at')),
            ],
            'symbol' => ['required', 'string', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * مصفوفة الرسائل المخصصة للتحقق
     */
    public function messages(): array
    {
        return [
            'name.required' => 'اسم وحدة القياس مطلوب.',
            'name.unique' => 'اسم وحدة القياس مستخدم من قبل.',
            'symbol.required' => 'رمز/اختصار وحدة القياس مطلوب.',
        ];
    }
}