<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests\Serials;

use App\Modules\Inventory\Models\StockSerial;
use Illuminate\Foundation\Http\FormRequest;

class DispatchSerialsToVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', StockSerial::class) ?? true;
    }

    public function rules(): array
    {
        return [
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
            'vendor_name' => [
                'nullable',
                'string',
                'max:255',
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
            'serial_ids'   => 'قائمة الأسطوانات المسلمة',
            'serial_ids.*' => 'الرقم التسلسلي للأسطوانة',
            'vendor_name'  => 'اسم المورد / مصنع التعبئة',
            'notes'        => 'ملاحظات التسليم',
        ];
    }

    public function messages(): array
    {
        return [
            'serial_ids.required' => 'يجب تحديد أسطوانة واحدة على الأقل للتسليم للمورد.',
            'serial_ids.array'    => 'يجب إرسال معرّفات الأسطوانات في مصفوفة صالحة.',
            'serial_ids.min'      => 'يجب تحديد أسطوانة واحدة على الأقل.',
            'serial_ids.*.exists' => 'إحدى الأسطوانات المحددة غير موجودة في النظام.',
            'vendor_name.max'     => 'اسم المورد يجب ألا يتجاوز 255 حرفاً.',
            'notes.max'           => 'الملاحظات لا يمكن أن تتجاوز 1000 حرف.',
        ];
    }
}