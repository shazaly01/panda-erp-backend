<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests\Units;

use App\Modules\Inventory\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitRequest extends FormRequest
{
    /**
     * تحديد ما إذا كان المستخدم مصرحاً له بتنفيذ هذا الطلب
     */
    public function authorize(): bool
    {
        $unit = $this->route('unit');

        return $unit && $this->user()->can('update', $unit);
    }

    /**
     * قواعد التحقق الخاصة بتحديث وحدة قياس
     */
    public function rules(): array
    {
        $unitId = $this->route('unit')?->id ?? $this->route('unit');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('inventory_units', 'name')
                    ->where(fn ($q) => $q->whereNull('deleted_at'))
                    ->ignore($unitId),
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