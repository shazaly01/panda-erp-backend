<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests\Adjustments;

use App\Modules\Inventory\Models\Adjustment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Adjustment::class);
    }

    public function rules(): array
    {
        return [
            'adjustment_number'           => ['required', 'string', 'max:100', Rule::unique('inventory_adjustments', 'adjustment_number')->whereNull('deleted_at')],
            'warehouse_id'                => ['required', 'integer', Rule::exists('inventory_warehouses', 'id')->whereNull('deleted_at')],
            'adjustment_date'             => ['required', 'date'],
            'type'                        => ['required', 'string', Rule::in(['opening_balance', 'physical_count', 'general_adjustment', 'damage', 'loss'])],
            'status'                      => ['nullable', 'string', Rule::in(['draft', 'approved'])],
            'auto_approve'                => ['nullable', 'boolean'],
            'notes'                       => ['nullable', 'string', 'max:1000'],
            'items'                       => ['required', 'array', 'min:1'],
            'items.*.product_id'          => ['required', 'integer', Rule::exists('inventory_products', 'id')->whereNull('deleted_at')],
            'items.*.product_unit_id'     => ['required', 'integer', Rule::exists('inventory_product_units', 'id')->whereNull('deleted_at')],
            'items.*.batch_id'            => ['nullable', 'integer', Rule::exists('inventory_stock_batches', 'id')->whereNull('deleted_at')],
            'items.*.current_quantity'    => ['required', 'numeric', 'min:0'],
            'items.*.actual_quantity'     => ['required', 'numeric', 'min:0'],
            'items.*.quantity_difference' => ['nullable', 'numeric'],
            'items.*.unit_cost'           => ['nullable', 'numeric', 'min:0'],
            'items.*.notes'               => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'adjustment_number.required' => 'رقم طلب التسوية مطلوب.',
            'adjustment_number.unique'   => 'رقم طلب التسوية مستخدم من قبل.',
            'warehouse_id.required'      => 'المستودع المراد إجراء التسوية عليه مطلوب.',
            'warehouse_id.exists'        => 'المستودع المحدد غير موجود.',
            'adjustment_date.required'   => 'تاريخ التسوية مطلوب.',
            'type.required'              => 'نوع التسوية مطلوب.',
            'type.in'                    => 'نوع التسوية المحدد غير صالح.',
            'items.required'             => 'يجب إضافة عنصر واحد على الأقل في أمر التسوية.',
            'items.min'                  => 'يجب إضافة عنصر واحد على الأقل في أمر التسوية.',
            'items.*.product_id.required'         => 'المنتج مطلوب لكل عنصر.',
            'items.*.product_id.exists'           => 'المنتج المحدد في أحد العناصر غير موجود.',
            'items.*.product_unit_id.required'    => 'وحدة المنتج مطلوبة لكل عنصر.',
            'items.*.product_unit_id.exists'      => 'وحدة المنتج المحددة غير موجودة.',
            'items.*.current_quantity.required'   => 'الكمية الحالية مطلوبة لكل عنصر.',
            'items.*.actual_quantity.required'    => 'الكمية الفعلية مطلوبة لكل عنصر.',
            'items.*.unit_cost.min'               => 'يجب ألا تكون تكلفة الوحدة بالسالب.',
        ];
    }
}