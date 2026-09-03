<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests\StockBatches;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStockBatchRequest extends FormRequest
{
    /**
     * تحديد ما إذا كان المستخدم مصرحاً له بتنفيذ هذا الطلب
     */
    public function authorize(): bool
    {
        $batch = $this->route('stock_batch') ?? $this->route('batch');

        return $this->user()->can('update', $batch);
    }

    /**
     * قواعد التحقق الخاصة بتعديل بيانات التشغيلة/الباتش
     */
    public function rules(): array
    {
        $batch = $this->route('stock_batch') ?? $this->route('batch');
        $batchId = is_object($batch) ? $batch->id : $batch;
        $companyId = $this->user()->company_id;

        // استخراج معرف المنتج إما من المدخلات أو من الكائن المخزن
        $productId = $this->input('product_id', is_object($batch) ? $batch->product_id : null);

        return [
            'product_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('inventory_products', 'id')->where(function ($query) use ($companyId) {
                    return $query->where('company_id', $companyId)
                                 ->whereNull('deleted_at');
                }),
            ],
            'batch_number' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('inventory_stock_batches', 'batch_number')
                    ->ignore($batchId)
                    ->where(function ($query) use ($companyId, $productId) {
                        return $query->where('company_id', $companyId)
                                     ->when($productId, fn ($q) => $q->where('product_id', $productId))
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