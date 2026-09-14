<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Http\Requests\Requisitions;

use App\Modules\Purchasing\Enums\RequisitionPriority;
use App\Modules\Purchasing\Models\PurchaseRequisition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseRequisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PurchaseRequisition::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'request_date' => ['nullable', 'date'],
            'required_date' => ['nullable', 'date', 'after_or_equal:request_date'],
            'priority' => ['required', Rule::enum(RequisitionPriority::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:inventory_products,id'],
            'items.*.item_name' => ['required_without:items.*.product_id', 'nullable', 'string', 'max:255'],
            'items.*.product_unit_id' => ['nullable', 'integer', 'exists:inventory_product_units,id'],
            'items.*.unit_name' => ['nullable', 'string', 'max:100'],
            'items.*.quantity_requested' => ['required', 'numeric', 'gt:0', 'max:9999999999.9999'],
            'items.*.estimated_unit_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999.9999'],
            'items.*.specifications' => ['nullable', 'string', 'max:2000'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'department_id' => 'القسم',
            'request_date' => 'تاريخ الطلب',
            'required_date' => 'تاريخ الحاجة',
            'priority' => 'الأولوية',
            'notes' => 'الملاحظات',
            'items' => 'بنود الطلب',
            'items.*.product_id' => 'المنتج',
            'items.*.item_name' => 'اسم الصنف',
            'items.*.product_unit_id' => 'وحدة القياس',
            'items.*.unit_name' => 'اسم الوحدة',
            'items.*.quantity_requested' => 'الكمية المطلوبة',
            'items.*.estimated_unit_cost' => 'التكلفة التقديرية للوحدة',
            'items.*.specifications' => 'المواصفات',
            'items.*.notes' => 'ملاحظات البند',
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.item_name.required_without' => 'يجب إدخال اسم الصنف في حال عدم اختيار منتج مسجل.',
        ];
    }
}