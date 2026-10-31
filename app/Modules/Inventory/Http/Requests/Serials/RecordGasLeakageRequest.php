<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests\Serials;

use App\Modules\Inventory\Models\StockSerial;
use Illuminate\Foundation\Http\FormRequest;

class RecordGasLeakageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', StockSerial::class) ?? true;
    }

    public function rules(): array
    {
        return [
            'serial_id' => [
                'required',
                'integer',
                'exists:inventory_serials,id',
            ],
            'new_gross_weight' => [
                'required',
                'numeric',
                'gt:0',
                'max:9999999999.9999',
            ],
            'warehouse_id' => [
                'nullable',
                'integer',
                'exists:inventory_warehouses,id',
            ],
            'reason' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'serial_id' => 'الرقم التسلسلي للأسطوانة',
            'new_gross_weight' => 'الوزن الإجمالي القائم الجديد على الميزان',
            'warehouse_id' => 'المستودع',
            'reason' => 'سبب التسريب / ملابسات الفاقد',
        ];
    }

    public function messages(): array
    {
        return [
            'serial_id.required' => 'يجب تحديد الأسطوانة المراد تسجيل التسريب لها.',
            'serial_id.exists' => 'الأسطوانة المحددة غير موجودة في سجلات النظام.',
            'new_gross_weight.required' => 'قراءة الميزان الجديدة مطلوبة.',
            'new_gross_weight.gt' => 'يجب أن يكون الوزن الجديد أكبر من الصفر.',
            'warehouse_id.exists' => 'المستودع المحدد غير موجود.',
            'reason.max' => 'سبب الفاقد لا يمكن أن يتجاوز 1000 حرف.',
        ];
    }
}