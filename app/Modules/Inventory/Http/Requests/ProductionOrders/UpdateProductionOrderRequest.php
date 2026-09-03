<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests\ProductionOrders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductionOrderRequest extends FormRequest
{
    /**
     * تحديد ما إذا كان المستخدم مصرحاً له بتنفيذ هذا الطلب
     */
    public function authorize(): bool
    {
        $productionOrder = $this->route('production_order');

        return $this->user()->can('update', $productionOrder);
    }

    /**
     * قواعد التحقق الخاصة بتعديل أمر إنتاج
     */
    public function rules(): array
    {
        $productionOrder = $this->route('production_order');
        $companyId = $this->user()->company_id;

        return [
            'order_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('inventory_production_orders', 'order_number')->ignore($productionOrder->id)->where(function ($query) use ($companyId) {
                    return $query->where('company_id', $companyId)
                                 ->whereNull('deleted_at');
                }),
            ],
            'bom_id' => [
                'required',
                'integer',
                Rule::exists('inventory_boms', 'id')->where(function ($query) use ($companyId) {
                    return $query->where('company_id', $companyId)
                                 ->whereNull('deleted_at');
                }),
            ],
            'warehouse_id' => [
                'required',
                'integer',
                Rule::exists('inventory_warehouses', 'id')->where(function ($query) use ($companyId) {
                    return $query->where('company_id', $companyId)
                                 ->whereNull('deleted_at');
                }),
            ],
            'planned_quantity' => [
                'required',
                'numeric',
                'gt:0',
            ],
            'start_date' => [
                'required',
                'date',
            ],
            'expected_completion_date' => [
                'nullable',
                'date',
                'after_or_equal:start_date',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * مصفوفة الرسائل المخصصة للتحقق
     */
    public function messages(): array
    {
        return [
            'order_number.required' => 'رقم أمر الإنتاج مطلوب.',
            'order_number.unique' => 'رقم أمر الإنتاج مستخدم من قبل في هذه الشركة.',
            'bom_id.required' => 'قائمة المكونات (BOM) مطلوبة.',
            'bom_id.exists' => 'قائمة المكونات المحددة غير موجودة.',
            'warehouse_id.required' => 'المستودع المستهدف لإيداع المنتج النهائي مطلوب.',
            'warehouse_id.exists' => 'المستودع المحدد غير موجود.',
            'planned_quantity.required' => 'الكمية المخطط إنتاجها مطلوبة.',
            'planned_quantity.gt' => 'يجب أن تكون الكمية المخططة أكبر من الصفر.',
            'start_date.required' => 'تاريخ بدء الإنتاج مطلوب.',
            'expected_completion_date.after_or_equal' => 'تاريخ اكمال الإنتاج المتوقع يجب أن يكون مساوياً أو بعد تاريخ البدء.',
        ];
    }
}
