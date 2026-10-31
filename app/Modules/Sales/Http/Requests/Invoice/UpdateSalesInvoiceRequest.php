<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSalesInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'partner_id' => ['sometimes', 'required', 'integer', 'exists:partners,id'],
            'branch_id' => ['nullable', 'integer'],
            'warehouse_id' => ['sometimes', 'required', 'integer', 'exists:inventory_warehouses,id'],
            'cost_center_id' => ['nullable', 'integer', 'exists:cost_centers,id'],
            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'channel' => ['nullable', 'string', 'in:standard,pos'],
            'order_type' => ['nullable', 'string', 'in:standard,dine_in,takeaway,delivery'],
            'table_label' => ['nullable', 'string', 'max:50'],
            'discount_type' => ['nullable', 'string', 'in:fixed,percentage'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'service_charge' => ['nullable', 'numeric', 'min:0'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'terms_conditions' => ['nullable', 'string', 'max:2000'],

            // بنود الفاتورة
            'items' => ['sometimes', 'required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer', 'exists:sales_invoice_items,id'],
            'items.*.product_id' => ['required', 'integer', 'exists:inventory_products,id'],
            'items.*.unit_id' => ['nullable', 'integer', 'exists:inventory_units,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_type' => ['nullable', 'string', 'in:fixed,percentage'],
            'items.*.discount_value' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.options' => ['nullable', 'array'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'partner_id.required' => 'العميل مطلوب.',
            'partner_id.exists' => 'العميل المحدد غير موجود بالنظام.',
            'warehouse_id.required' => 'المستودع مطلوب لخصم وحركة البضاعة.',
            'warehouse_id.exists' => 'المستودع المحدد غير موجود بالنظام.',
            'items.required' => 'يجب إدراج صنف واحد على الأقل في الفاتورة.',
            'items.min' => 'يجب إدراج صنف واحد على الأقل في الفاتورة.',
            'items.*.product_id.required' => 'الصنف مطلوب لكل سطر في الفاتورة.',
            'items.*.product_id.exists' => 'أحد الأصناف المحددة غير موجود بالمخزون.',
            'items.*.quantity.required' => 'الكمية مطلوبة.',
            'items.*.quantity.gt' => 'يجب أن تكون الكمية أكبر من الصفر.',
            'items.*.unit_price.required' => 'سعر الوحدة مطلوب.',
            'items.*.unit_price.min' => 'لا يمكن أن يكون سعر الوحدة بالسالب.',
        ];
    }
}