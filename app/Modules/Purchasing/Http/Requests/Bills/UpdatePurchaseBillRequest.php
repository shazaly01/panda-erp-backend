<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Http\Requests\Bills;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        $bill = $this->route('bill');

        return $bill && ($this->user()?->can('update', $bill) ?? false);
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:partners,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:inventory_warehouses,id'],
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchasing_orders,id'],
            'receipt_id' => ['nullable', 'integer', 'exists:purchasing_receipts,id'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0', 'max:9999999999.9999'],
            'bill_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:bill_date'],
            'supplier_bill_number' => ['nullable', 'string', 'max:100'],
            'discount_type' => ['nullable', 'string', 'in:fixed,percentage'],
            'discount_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999.9999'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999.9999'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer', 'exists:purchasing_bill_items,id'],
            'items.*.purchase_order_item_id' => ['nullable', 'integer', 'exists:purchasing_order_items,id'],
            'items.*.receipt_item_id' => ['nullable', 'integer', 'exists:purchasing_receipt_items,id'],
            'items.*.product_id' => ['required', 'integer', 'exists:inventory_products,id'],
            'items.*.product_unit_id' => ['required', 'integer', 'exists:inventory_product_units,id'],
            'items.*.account_id' => ['nullable', 'integer', 'exists:accounts,id'],
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
            'warehouse_id' => 'المستودع',
            'purchase_order_id' => 'أمر الشراء',
            'receipt_id' => 'سند الاستلام المخزني',
            'currency_id' => 'العملة',
            'exchange_rate' => 'سعر الصرف',
            'bill_date' => 'تاريخ الفاتورة',
            'due_date' => 'تاريخ الاستحقاق',
            'supplier_bill_number' => 'رقم فاتورة المورد',
            'discount_type' => 'نوع الخصم',
            'discount_value' => 'قيمة الخصم',
            'shipping_cost' => 'تكلفة الشحن',
            'notes' => 'الملاحظات',
            'items' => 'بنود الفاتورة',
            'items.*.id' => 'معرف البند',
            'items.*.purchase_order_item_id' => 'بند أمر الشراء',
            'items.*.receipt_item_id' => 'بند سند الاستلام',
            'items.*.product_id' => 'المنتج',
            'items.*.product_unit_id' => 'وحدة القياس',
            'items.*.account_id' => 'الحساب المالي',
            'items.*.quantity' => 'الكمية',
            'items.*.unit_price' => 'سعر الوحدة',
            'items.*.discount_percentage' => 'نسبة الخصم',
            'items.*.tax_rate' => 'نسبة الضريبة',
            'items.*.notes' => 'ملاحظات البند',
        ];
    }
}