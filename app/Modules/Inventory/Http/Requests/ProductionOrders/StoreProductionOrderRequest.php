<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests\ProductionOrders;

use App\Modules\Inventory\Models\ProductionOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductionOrderRequest extends FormRequest
{
    /**
     * تحديد ما إذا كان المستخدم مصرحاً له بتنفيذ هذا الطلب
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', ProductionOrder::class);
    }

    /**
     * قواعد التحقق الخاصة بإنشاء أمر إنتاج جديد
     */
    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'order_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('inventory_production_orders', 'order_number')->where(function ($query) use ($companyId) {
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
            'product_id' => [
                'required',
                'integer',
                Rule::exists('inventory_products', 'id')->where(function ($query) use ($companyId) {
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
            'production_date' => [
                'required',
                'date',
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
            'product_id.required' => 'المنتج النهائي مطلوب.',
            'product_id.exists' => 'المنتج النهائي المحدد غير موجود.',
            'warehouse_id.required' => 'المستودع المستهدف لإيداع المنتج النهائي مطلوب.',
            'warehouse_id.exists' => 'المستودع المحدد غير موجود.',
            'planned_quantity.required' => 'الكمية المخطط إنتاجها مطلوبة.',
            'planned_quantity.gt' => 'يجب أن تكون الكمية المخططة أكبر من الصفر.',
            'production_date.required' => 'تاريخ الإنتاج مطلوب.',
            'production_date.date' => 'تاريخ الإنتاج يجب أن يكون تاريخاً صالحاً.',
        ];
    }
}