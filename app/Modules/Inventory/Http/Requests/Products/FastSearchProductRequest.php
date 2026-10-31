<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class FastSearchProductRequest extends FormRequest
{
    /**
     * تحديد صلاحية تنفيذ الطلب.
     * يبقى التحقق من صلاحية عرض الأصناف داخل ProductController كما هو.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * توحيد اسم المستودع قبل التحقق.
     *
     * بعض الواجهات الحالية ترسل store_id، بينما ProductController يقرأ
     * warehouse_id. نقبل الاسم القديم كاسم بديل دون تغيير المتحكم أو الواجهات.
     * لا نحذف store_id حتى يمكن التحقق من التعارض عند إرسال الاسمين معاً.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('warehouse_id') && $this->filled('store_id')) {
            $this->merge([
                'warehouse_id' => $this->input('store_id'),
            ]);
        }
    }

    /**
     * قواعد التحقق من مدخلات البحث السريع.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'max:255'],
            'warehouse_id' => ['nullable', 'integer'],
            'store_id' => ['nullable', 'integer'],
            'price_list_id' => ['nullable', 'integer'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    /**
     * لا نقبل طلباً يحدد مستودعين مختلفين بالاسمين البديلين.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('warehouse_id') || $validator->errors()->has('store_id')) {
                return;
            }

            if ($this->filled('warehouse_id') && $this->filled('store_id')
                && (int) $this->input('warehouse_id') !== (int) $this->input('store_id')) {
                $validator->errors()->add(
                    'warehouse_id',
                    'معرف المستودع متعارض: يجب أن يشير warehouse_id وstore_id إلى المستودع نفسه.'
                );
            }
        });
    }

    /**
     * الرسائل المخصصة لأخطاء التحقق.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'query.required' => 'حقل البحث أو كود الباركود مطلوب.',
            'query.string' => 'يجب أن يكون نص البحث قيمة نصية صالحة.',
            'warehouse_id.integer' => 'معرف المستودع يجب أن يكون رقماً صحيحاً.',
            'store_id.integer' => 'معرف المستودع المرسل باسم store_id يجب أن يكون رقماً صحيحاً.',
            'price_list_id.integer' => 'معرف قائمة الأسعار يجب أن يكون رقماً صحيحاً.',
            'limit.integer' => 'الحد الأقصى لعدد النتائج يجب أن يكون رقماً صحيحاً.',
            'limit.min' => 'يجب ألا يقل عدد النتائج عن 1.',
            'limit.max' => 'الحد الأقصى لعدد النتائج هو 50 نتيجة.',
        ];
    }
}
