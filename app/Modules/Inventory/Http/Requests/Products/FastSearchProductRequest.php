<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;

class FastSearchProductRequest extends FormRequest
{
    /**
     * تحديد صلاحية تنفيذ الطلب
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * قواعد التحقق من مدخلات البحث السريع
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'max:255'],
            'warehouse_id' => ['nullable', 'integer'],
            'price_list_id' => ['nullable', 'integer'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    /**
     * الرسائل المخصصة لأخطاء التحقق
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'query.required' => 'حقل البحث أو كود الباركود مطلوب.',
            'query.string' => 'يجب أن يكون نص البحث قيمة نصية صالحة.',
            'warehouse_id.integer' => 'معرف المستودع يجب أن يكون رقماً صحيحاً.',
            'price_list_id.integer' => 'معرف قائمة الأسعار يجب أن يكون رقماً صحيحاً.',
            'limit.integer' => 'الحد الأقصى لعدد النتائج يجب أن يكون رقماً صحيحاً.',
            'limit.min' => 'يجب ألا يقل عدد النتائج عن 1.',
            'limit.max' => 'الحد الأقصى لعدد النتائج هو 50 نتيجة.',
        ];
    }
}