<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests\Serials;

use App\Modules\Inventory\Models\StockSerial;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStockSerialRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var StockSerial|null $serial */
        $serial = $this->route('serial');

        return $serial !== null
            ? ($this->user()?->can('update', $serial) ?? false)
            : ($this->user()?->can('update', StockSerial::class) ?? false);
    }

    public function rules(): array
    {
        /** @var StockSerial|null $serial */
        $serial = $this->route('serial');
        $serialId = $serial?->id;
        $productId = $serial?->product_id;

        return [
            'serial_number' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('inventory_serials', 'serial_number')
                    ->ignore($serialId)
                    ->where(function ($query) use ($productId) {
                        if ($productId) {
                            $query->where('product_id', $productId);
                        }
                        return $query->whereNull('deleted_at');
                    }),
            ],
            'tare_weight' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999999999.9999',
            ],
            'location_id' => [
                'nullable',
                'integer',
                'exists:inventory_warehouse_locations,id',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'revert_to_empty' => [
                'nullable',
                'boolean',
            ],
            'warehouse_id' => [
                'nullable',
                'required_if:revert_to_empty,true',
                'integer',
                'exists:inventory_warehouses,id',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'serial_number'   => 'الرقم التسلسلي للأسطوانة',
            'tare_weight'     => 'الوزن الفارغ للحديد',
            'location_id'     => 'الموقع / الرف التخزيني',
            'notes'           => 'الملاحظات',
            'revert_to_empty' => 'التراجع وإعادة الأسطوانة كفارغة بالمستودع',
            'warehouse_id'    => 'المستودع المستلم عند التراجع',
        ];
    }

    public function messages(): array
    {
        return [
            'serial_number.required'   => 'الرقم التسلسلي مطلوب عند التعديل.',
            'serial_number.unique'     => 'الرقم التسلسلي مستخدم بالفعل لنفس الصنف.',
            'tare_weight.numeric'      => 'الوزن الفارغ يجب أن يكون قيمة رقمية.',
            'tare_weight.min'          => 'الوزن الفارغ لا يمكن أن يكون سالباً.',
            'location_id.exists'       => 'الموقع أو الرف المحدد غير موجود في سجلات النظام.',
            'warehouse_id.required_if' => 'يجب تحديد المستودع لإعادة الأسطوانة إليه عند التراجع عن التسليم للمورد.',
            'warehouse_id.exists'      => 'المستودع المحدد غير موجود.',
            'notes.max'                => 'الملاحظات لا يمكن أن تتجاوز 1000 حرف.',
        ];
    }
}