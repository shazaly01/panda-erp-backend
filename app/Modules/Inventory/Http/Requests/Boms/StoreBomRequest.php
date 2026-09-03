<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests\Boms;

use App\Modules\Inventory\Models\Bom;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBomRequest extends FormRequest
{
    /**
     * تحديد ما إذا كان المستخدم مصرحاً له بتنفيذ هذا الطلب
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Bom::class);
    }

    /**
     * قواعد التحقق الخاصة بإنشاء قائمة مكونات جديدة
     */
    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'code' => ['required', 'string', 'max:100', Rule::unique('inventory_boms', 'code')->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at'))],
            'name' => ['required', 'string', 'max:255'],
            'product_id' => ['required', 'integer', Rule::exists('inventory_products', 'id')->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at'))],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.raw_material_id' => ['required', 'integer', Rule::exists('inventory_products', 'id')->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at'))],
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
            'code.required' => 'كود قائمة المكونات (BOM) مطلوب.',
            'code.unique' => 'كود قائمة المكونات مستخدم من قبل في هذه الشركة.',
            'name.required' => 'اسم قائمة المكونات مطلوب.',
            'product_id.required' => 'المنتج التام/النهائي مطلوب.',
            'product_id.exists' => 'المنتج المحدد غير موجود.',
            'quantity.required' => 'الكمية المنتجة مطلوبة.',
            'quantity.gt' => 'يجب أن تكون الكمية المنتجة أكبر من الصفر.',
            'items.required' => 'يجب إضافة مادة خام واحدة على الأقل لقائمة المكونات.',
            'items.min' => 'يجب إضافة مادة خام واحدة على الأقل لقائمة المكونات.',
            'items.*.raw_material_id.required' => 'المادة الخام مطلوبة لكل عنصر.',
            'items.*.raw_material_id.exists' => 'المادة الخام المحددة في أحد العناصر غير موجودة.',
            'items.*.product_unit_id.required' => 'وحدة قياس المادة الخام مطلوبة.',
            'items.*.product_unit_id.exists' => 'وحدة القياس المحددة للمادة الخام غير موجودة.',
            'items.*.quantity.required' => 'كمية المادة الخام مطلوبة.',
            'items.*.quantity.gt' => 'يجب أن تكون كمية المادة الخام أكبر من الصفر.',
            'items.*.waste_percentage.max' => 'نسبة الهالك لا يمكن أن تتجاوز 100%.',
        ];
    }
}