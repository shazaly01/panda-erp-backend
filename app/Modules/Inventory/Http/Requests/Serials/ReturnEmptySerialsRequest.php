<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests\Serials;

use App\Modules\Inventory\Models\StockSerial;
use Illuminate\Foundation\Http\FormRequest;

class ReturnEmptySerialsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', StockSerial::class) ?? true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => [
                'required',
                'integer',
                'exists:inventory_warehouses,id',
            ],
            'serial_ids' => [
                'required',
                'array',
                'min:1',
            ],
            'serial_ids.*' => [
                'required',
                'integer',
                'exists:inventory_serials,id',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'warehouse_id' => 'المستودع الرئيسي المستلم',
            'serial_ids'   => 'قائمة الأسطوانات',
            'serial_ids.*' => 'الرقم التسلسلي للأسطوانة',
            'notes'        => 'ملاحظات الإرجاع',
        ];
    }

    public function messages(): array
    {
        return [
            'warehouse_id.required' => 'يجب تحديد المستودع المراد إرجاع الأسطوانات إليه.',
            'warehouse_id.exists'   => 'المستودع المحدد غير موجود في سجلات النظام.',
            'serial_ids.required'   => 'يجب تحديد أسطوانة واحدة على الأقل لإرجاعها.',
            'serial_ids.array'      => 'يجب إرسال معرّفات الأسطوانات في مصفوفة صالحة.',
            'serial_ids.min'        => 'يجب تحديد أسطوانة واحدة على الأقل.',
            'serial_ids.*.exists'   => 'إحدى الأسطوانات المحددة غير موجودة في النظام.',
            'notes.max'             => 'الملاحظات لا يمكن أن تتجاوز 1000 حرف.',
        ];
    }
}