<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Requests\Return;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalesReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sales_invoice_id' => ['nullable', 'integer', 'exists:sales_invoices,id'],
            'shift_id' => ['nullable', 'integer', 'exists:pos_shifts,id'],
            'return_date' => ['nullable', 'date'],
            'partner_id' => ['required', 'integer', 'exists:partners,id'],
            'branch_id' => ['nullable', 'integer'],
            'warehouse_id' => ['required', 'integer', 'exists:inventory_warehouses,id'],
            'cost_center_id' => ['nullable', 'integer', 'exists:cost_centers,id'],
            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],

            // بنود المرتجع
            'items' => ['required', 'array', 'min:1'],
            'items.*.sales_invoice_item_id' => ['nullable', 'integer', 'exists:sales_invoice_items,id'],
            'items.*.product_id' => ['required', 'integer', 'exists:inventory_products,id'],
            'items.*.unit_id' => ['nullable', 'integer', 'exists:inventory_units,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.item_condition' => ['nullable', 'string', 'in:good,damaged,scrap'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'partner_id.required' => 'العميل مطلوب لإثبات المرتجع.',
            'partner_id.exists' => 'العميل المحدد غير موجود بالنظام.',
            'warehouse_id.required' => 'مستودع الاستلام مطلوب لإرجاع البضاعة.',
            'warehouse_id.exists' => 'المستودع المحدد غير موجود بالنظام.',
            'shift_id.exists' => 'وردية نقطة البيع المحددة غير صالحة أو غير موجودة.',
            'items.required' => 'يجب إدراج صنف واحد على الأقل في المرتجع.',
            'items.min' => 'يجب إدراج صنف واحد على الأقل في المرتجع.',
            'items.*.product_id.required' => 'الصنف مطلوب لكل بند مرتجع.',
            'items.*.product_id.exists' => 'أحد الأصناف المحددة غير موجود بالمخزون.',
            'items.*.quantity.required' => 'كمية المرتجع مطلوبة.',
            'items.*.quantity.gt' => 'يجب أن تكون كمية المرتجع أكبر من الصفر.',
            'items.*.unit_price.required' => 'سعر الصنف المرتجع مطلوب.',
            'items.*.unit_price.min' => 'لا يمكن أن يكون سعر الصنف بالسالب.',
        ];
    }
}