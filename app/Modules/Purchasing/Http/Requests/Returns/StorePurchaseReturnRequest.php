<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Http\Requests\Returns;

use App\Modules\Purchasing\Models\PurchaseReturn;
use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PurchaseReturn::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:partners,id'],
            'warehouse_id' => ['required', 'integer', 'exists:inventory_warehouses,id'],
            'bill_id' => ['nullable', 'integer', 'exists:purchasing_bills,id'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0', 'max:9999999999.9999'],
            'return_date' => ['required', 'date'],
            'return_reason' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.bill_item_id' => ['nullable', 'integer', 'exists:purchasing_bill_items,id'],
            'items.*.product_id' => ['required', 'integer', 'exists:inventory_products,id'],
            'items.*.product_unit_id' => ['required', 'integer', 'exists:inventory_product_units,id'],
            'items.*.location_id' => ['nullable', 'integer', 'exists:inventory_warehouse_locations,id'],
            'items.*.batch_id' => ['nullable', 'integer', 'exists:inventory_stock_batches,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:9999999999.9999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999.9999'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.reason' => ['nullable', 'string', 'max:1000'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'supplier_id' => 'المورد',
            'warehouse_id' => 'المستودع',
            'bill_id' => 'فاتورة الشراء',
            'currency_id' => 'العملة',
            'exchange_rate' => 'سعر الصرف',
            'return_date' => 'تاريخ الإرجاع',
            'return_reason' => 'سبب الإرجاع',
            'notes' => 'الملاحظات',
            'items' => 'بنود المردود',
            'items.*.bill_item_id' => 'بند فاتورة الشراء',
            'items.*.product_id' => 'المنتج',
            'items.*.product_unit_id' => 'وحدة القياس',
            'items.*.location_id' => 'موقع التخزين/الرف',
            'items.*.batch_id' => 'رقم التشغيلة/الدفعة',
            'items.*.quantity' => 'الكمية المرتجعة',
            'items.*.unit_price' => 'سعر الوحدة',
            'items.*.tax_rate' => 'نسبة الضريبة',
            'items.*.reason' => 'سبب إرجاع الصنف',
            'items.*.notes' => 'ملاحظات البند',
        ];
    }
}