<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests\Boms;

use App\Modules\Inventory\Models\Bom;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBomRequest extends FormRequest
{
    /**
     * تحديد ما إذا كان المستخدم مصرحاً له بتنفيذ هذا الطلب
     */
    public function authorize(): bool
    {
        $bom = $this->route('bom');

        return $this->user()->can('update', $bom);
    }

    /**
     * قواعد التحقق الخاصة بتحديث قائمة المكونات
     */
    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        $bom = $this->route('bom');
        $bomId = $bom?->id ?? $bom;

        return [
            'bom_number' => ['required', 'string', 'max:100', Rule::unique('inventory_boms', 'bom_number')->ignore($bomId)->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at'))],
            'product_id' => ['required', 'integer', Rule::exists('inventory_products', 'id')->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at'))],
            'product_unit_id' => ['required', 'integer', Rule::exists('inventory_product_units', 'id')->whereNull('deleted_at')],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.material_product_id' => ['required', 'integer', Rule::exists('inventory_products', 'id')->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at'))],
            'items.*.product_unit_id' => ['required', 'integer', Rule::exists('inventory_product_units', 'id')->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.waste_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * مصفوفة الرسائل المخصصة للتحقق
     */
    public function messages(): array
    {
        return [
            'bom_number.required' => 'رقم قائمة المكونات (BOM) مطلوب.',
            'bom_number.unique' => 'رقم قائمة المكونات مستخدم من قبل في هذه الشركة.',
            'product_id.required' => 'المنتج التام/النهائي مطلوب.',
            'product_id.exists' => 'المنتج المحدد غير موجود.',
            'product_unit_id.required' => 'وحدة القياس للمنتج النهائي مطلوبة.',
            'product_unit_id.exists' => 'وحدة القياس المحددة غير موجودة.',
            'quantity.required' => 'الكمية المنتجة مطلوبة.',
            'quantity.gt' => 'يجب أن تكون الكمية المنتجة أكبر من الصفر.',
            'items.required' => 'يجب إضافة مادة خام واحدة على الأقل لقائمة المكونات.',
            'items.min' => 'يجب إضافة مادة خام واحدة على الأقل لقائمة المكونات.',
            'items.*.material_product_id.required' => 'المادة الخام مطلوبة لكل عنصر.',
            'items.*.material_product_id.exists' => 'المادة الخام المحددة في أحد العناصر غير موجودة.',
            'items.*.product_unit_id.required' => 'وحدة قياس المادة الخام مطلوبة.',
            'items.*.product_unit_id.exists' => 'وحدة القياس المحددة للمادة الخام غير موجودة.',
            'items.*.quantity.required' => 'كمية المادة الخام مطلوبة.',
            'items.*.quantity.gt' => 'يجب أن تكون كمية المادة الخام أكبر من الصفر.',
            'items.*.waste_percentage.max' => 'نسبة الهالك لا يمكن أن تتجاوز 100%.',
        ];
    }
}
