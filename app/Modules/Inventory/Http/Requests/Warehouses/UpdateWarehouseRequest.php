<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests\Warehouses;

use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWarehouseRequest extends FormRequest
{
    /**
     * تحديد ما إذا كان المستخدم مصرحاً له بتنفيذ هذا الطلب
     */
    public function authorize(): bool
    {
        $warehouse = $this->route('warehouse');

        return $warehouse && $this->user()->can('update', $warehouse);
    }

    /**
     * قواعد التحقق الخاصة بتحديث مستودع
     */
    public function rules(): array
    {
        $warehouseId = $this->route('warehouse')?->id ?? $this->route('warehouse');

        return [
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('inventory_warehouses', 'code')
                    ->where(fn ($q) => $q->whereNull('deleted_at'))
                    ->ignore($warehouseId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:30'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'account_id' => [
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')
                    ->where(fn ($q) => $q->where('is_transactional', true)->whereNull('deleted_at')),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * مصفوفة الرسائل المخصصة للتحقق
     */
    public function messages(): array
    {
        return [
            'code.required' => 'رمز المستودع مطلوب.',
            'code.unique' => 'رمز المستودع مستخدم من قبل.',
            'name.required' => 'اسم المستودع مطلوب.',
            'manager_id.exists' => 'المسؤول المحدد غير موجود في النظام.',
            'account_id.exists' => 'الحساب المالي المحدد غير صالح، يجب اختيار حساب فرعي نشط يقبل القيود.',
        ];
    }
}