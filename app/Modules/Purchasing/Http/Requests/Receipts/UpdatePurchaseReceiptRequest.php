<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Http\Requests\Receipts;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        $receipt = $this->route('receipt');

        return $receipt && ($this->user()?->can('update', $receipt) ?? false);
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:partners,id'],
            'warehouse_id' => ['required', 'integer', 'exists:inventory_warehouses,id'],
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchasing_orders,id'],
            'receipt_date' => ['required', 'date'],
            'supplier_delivery_note' => ['nullable', 'string', 'max:100'],
            'waybill_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer', 'exists:purchasing_receipt_items,id'],
            'items.*.purchase_order_item_id' => ['nullable', 'integer', 'exists:purchasing_order_items,id'],
            'items.*.product_id' => ['required', 'integer', 'exists:inventory_products,id'],
            'items.*.product_unit_id' => ['required', 'integer', 'exists:inventory_product_units,id'],
            'items.*.location_id' => ['nullable', 'integer', 'exists:inventory_warehouse_locations,id'],
            'items.*.batch_id' => ['nullable', 'integer', 'exists:inventory_stock_batches,id'],
            'items.*.quantity_received' => ['required', 'numeric', 'gt:0', 'max:9999999999.9999'],
            'items.*.quantity_accepted' => ['required', 'numeric', 'min:0', 'lte:items.*.quantity_received', 'max:9999999999.9999'],
            'items.*.quantity_rejected' => ['nullable', 'numeric', 'min:0', 'max:9999999999.9999'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999.9999'],
            'items.*.rejection_reason' => ['nullable', 'string', 'max:1000'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'supplier_id' => 'المورد',
            'warehouse_id' => 'المستودع',
            'purchase_order_id' => 'أمر الشراء',
            'receipt_date' => 'تاريخ الاستلام',
            'supplier_delivery_note' => 'إذن تسليم المورد',
            'waybill_number' => 'بوليصة الشحن',
            'notes' => 'الملاحظات',
            'items' => 'بنود الاستلام',
            'items.*.id' => 'معرف البند',
            'items.*.purchase_order_item_id' => 'بند أمر الشراء',
            'items.*.product_id' => 'المنتج',
            'items.*.product_unit_id' => 'وحدة القياس',
            'items.*.location_id' => 'موقع التخزين/الرف',
            'items.*.batch_id' => 'رقم التشغيلة/الدفعة',
            'items.*.quantity_received' => 'الكمية المستلمة',
            'items.*.quantity_accepted' => 'الكمية المقبولة',
            'items.*.quantity_rejected' => 'الكمية المرفوضة',
            'items.*.unit_cost' => 'تكلفة الوحدة',
            'items.*.rejection_reason' => 'سبب الرفض',
            'items.*.notes' => 'ملاحظات البند',
        ];
    }
}