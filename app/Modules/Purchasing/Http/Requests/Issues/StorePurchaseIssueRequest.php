<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Http\Requests\Issues;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'requisition_id' => ['nullable', 'integer', 'exists:purchasing_requisitions,id'],
            'warehouse_id' => ['required', 'integer', 'exists:inventory_warehouses,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'recipient_id' => ['nullable', 'integer', 'exists:users,id'],
            'issue_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.requisition_item_id' => ['nullable', 'integer', 'exists:purchasing_requisition_items,id'],
            'items.*.product_id' => ['required', 'integer', 'exists:inventory_products,id'],
            'items.*.product_unit_id' => ['required', 'integer', 'exists:inventory_product_units,id'],
            'items.*.location_id' => ['nullable', 'integer', 'exists:inventory_warehouse_locations,id'],
            'items.*.batch_id' => ['nullable', 'integer', 'exists:inventory_stock_batches,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'requisition_id' => 'طلب الشراء الداخلي',
            'warehouse_id' => 'المستودع',
            'department_id' => 'القسم',
            'recipient_id' => 'المستلم',
            'issue_date' => 'تاريخ الصرف',
            'notes' => 'الملاحظات',
            'items' => 'بنود الصرف',
            'items.*.requisition_item_id' => 'بند طلب الشراء',
            'items.*.product_id' => 'الصنف',
            'items.*.product_unit_id' => 'وحدة القياس',
            'items.*.location_id' => 'موقع التخزين / الرف',
            'items.*.batch_id' => 'رقم التشغيلة / الدفعة',
            'items.*.quantity' => 'الكمية المنصرفة',
            'items.*.unit_cost' => 'تكلفة الوحدة',
            'items.*.notes' => 'ملاحظات البند',
        ];
    }
}