<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Http\Requests\Requisitions;

use Illuminate\Foundation\Http\FormRequest;

class TriagePurchaseRequisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:inventory_warehouses,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.requisition_item_id' => ['required', 'integer', 'exists:purchasing_requisition_items,id'],
            'items.*.action' => ['required', 'string', 'in:issue,purchase'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:inventory_products,id'],
            'items.*.product_unit_id' => ['nullable', 'integer', 'exists:inventory_product_units,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'warehouse_id' => 'المستودع المختار للصرف',
            'items' => 'بنود الفرز والتوزيع',
            'items.*.requisition_item_id' => 'بند طلب الشراء',
            'items.*.action' => 'الإجراء المختار (صرف أو شراء)',
            'items.*.product_id' => 'الصنف المطابق',
            'items.*.product_unit_id' => 'وحدة القياس',
            'items.*.quantity' => 'الكمية المنفذة',
            'items.*.unit_cost' => 'التكلفة التقديرية',
            'items.*.notes' => 'ملاحظات البند',
        ];
    }
}