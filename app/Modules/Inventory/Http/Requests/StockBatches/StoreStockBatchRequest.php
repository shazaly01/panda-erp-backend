<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests\StockBatches;

use App\Modules\Inventory\Models\StockBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockBatchRequest extends FormRequest
{
    /**
     * تحديد ما إذا كان المستخدم مصرحاً له بتنفيذ هذا الطلب
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', StockBatch::class);
    }

    /**
     * قواعد التحقق الخاصة بإنشاء تشغيلة/باتش جديد
     */
    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'product_id' => [
                'required',
                'integer',
                Rule::exists('inventory_products', 'id')->where(function ($query) use ($companyId) {
                    return $query->where('company_id', $companyId)
                                 ->whereNull('deleted_at');
                }),
            ],
            'batch_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('inventory_stock_batches', 'batch_number')->where(function ($query) use ($companyId) {
                    return $query->where('company_id', $companyId)
                                 ->where('product_id', $this->input('product_id'))
                                 ->whereNull('deleted_at');
                }),
            ],
            'manufacturing_date' => [
                'nullable',
                'date',
            ],
            'expiry_date' => [
                'nullable',
                'date',
                'after_or_equal:manufacturing_date',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    /**
     * مصفوفة الرسائل المخصصة للتحقق
     */
    public function messages(): array
    {
        return [
            'product_id.required' => 'المنتج المرتبط بالتشغيلة مطلوب.',
            'product_id.exists' => 'المنتج المحدد غير موجود في هذه الشركة.',
            'batch_number.required' => 'رقم التشغيلة/الباتش مطلوب.',
            'batch_number.unique' => 'رقم التشغيلة مستخدم من قبل لهذا المنتج في هذه الشركة.',
            'manufacturing_date.date' => 'تاريخ التصنيع يجب أن يكون تاريخاً صالحاً.',
            'expiry_date.date' => 'تاريخ الانتهاء يجب أن يكون تاريخاً صالحاً.',
            'expiry_date.after_or_equal' => 'تاريخ الانتهاء يجب أن يكون مساوياً أو بعد تاريخ التصنيع.',
            'is_active.boolean' => 'حالة التفعيل يجب أن تكون قيمة منطقية.',
        ];
    }
}