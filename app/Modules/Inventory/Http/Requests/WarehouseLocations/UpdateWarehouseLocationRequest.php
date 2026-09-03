<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests\WarehouseLocations;

use App\Modules\Inventory\Models\WarehouseLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWarehouseLocationRequest extends FormRequest
{
    /**
     * تحديد ما إذا كان المستخدم مصرحاً له بتنفيذ هذا الطلب
     */
    public function authorize(): bool
    {
        $location = $this->route('warehouse_location') ?? $this->route('location');

        return $location && $this->user()->can('update', $location);
    }

    /**
     * قواعد التحقق الخاصة بتحديث موقع مستودع
     */
    public function rules(): array
    {
        $location = $this->route('warehouse_location') ?? $this->route('location');
        $locationId = $location?->id ?? $location;
        $warehouseId = $this->input('warehouse_id', $location?->warehouse_id);

        return [
            'warehouse_id' => [
                'required',
                'integer',
                Rule::exists('inventory_warehouses', 'id')->where(fn ($q) => $q->whereNull('deleted_at')),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                'different:id',
                Rule::exists('inventory_warehouse_locations', 'id')->where(fn ($q) => $q->where('warehouse_id', $warehouseId)->whereNull('deleted_at')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('inventory_warehouse_locations', 'code')
                    ->where(fn ($q) => $q->where('warehouse_id', $warehouseId)->whereNull('deleted_at'))
                    ->ignore($locationId),
            ],
            'type' => ['sometimes', 'string', 'in:aisle,rack,shelf,bin'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * مصفوفة الرسائل المخصصة للتحقق
     */
    public function messages(): array
    {
        return [
            'warehouse_id.required' => 'المستودع مطلوب.',
            'warehouse_id.exists' => 'المستودع المحدد غير موجود.',
            'parent_id.exists' => 'الموقع الأب المحدد غير موجود في هذا المستودع.',
            'parent_id.different' => 'لا يمكن اختيار نفس الموقع ليكون موقعاً أباً لنفسه.',
            'name.required' => 'اسم الموقع مطلوب.',
            'code.unique' => 'رمز الموقع مستخدم من قبل في هذا المستودع.',
            'type.in' => 'نوع الموقع يجب أن يكون أحد الخيارات التالية: aisle, rack, shelf, bin.',
        ];
    }
}