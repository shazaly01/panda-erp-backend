<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Http\Requests\Orders;

use App\Modules\Purchasing\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PurchaseOrder::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:partners,id'],
            'requisition_id' => ['nullable', 'integer', 'exists:purchasing_requisitions,id'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0', 'max:9999999999.9999'],
            'order_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'payment_terms' => ['nullable', 'string', 'max:100'],
            'discount_type' => ['nullable', 'string', 'in:fixed,percentage'],
            'discount_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999.9999'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999.9999'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'terms_and_conditions' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.requisition_item_id' => ['nullable', 'integer', 'exists:purchasing_requisition_items,id'],
            'items.*.product_id' => ['required', 'integer', 'exists:inventory_products,id'],
            'items.*.product_unit_id' => ['required', 'integer', 'exists:inventory_product_units,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:9999999999.9999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999.9999'],
            'items.*.discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'supplier_id' => 'المورد',
            'requisition_id' => 'طلب الشراء المرتبط',
            'currency_id' => 'العملة',
            'exchange_rate' => 'سعر الصرف',
            'order_date' => 'تاريخ أمر الشراء',
            'expected_delivery_date' => 'تاريخ التوريد المتوقع',
            'payment_terms' => 'شروط الدفع',
            'discount_type' => 'نوع الخصم',
            'discount_value' => 'قيمة الخصم',
            'shipping_cost' => 'تكلفة الشحن',
            'notes' => 'الملاحظات',
            'terms_and_conditions' => 'الشروط والأحكام',
            'items' => 'بنود أمر الشراء',
            'items.*.requisition_item_id' => 'بند طلب الشراء',
            'items.*.product_id' => 'المنتج',
            'items.*.product_unit_id' => 'وحدة القياس',
            'items.*.quantity' => 'الكمية',
            'items.*.unit_price' => 'سعر الوحدة',
            'items.*.discount_percentage' => 'نسبة الخصم',
            'items.*.tax_rate' => 'نسبة الضريبة',
            'items.*.notes' => 'ملاحظات البند',
        ];
    }
}