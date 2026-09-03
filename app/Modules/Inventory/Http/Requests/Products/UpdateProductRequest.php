<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests\Products;

use App\Modules\Inventory\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    /**
     * تحديد ما إذا كان المستخدم مصرحاً له بتنفيذ هذا الطلب
     */
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $this->user()->can('update', $product);
    }

    /**
     * قواعد التحقق المطبقة على طلب تحديث المنتج
     */
    public function rules(): array
    {
        $product = $this->route('product');
        $productId = is_object($product) ? $product->id : $product;
        $companyId = $this->user()->company_id;

        return [
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('inventory_categories', 'id')->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at')),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'sku' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('inventory_products', 'sku')
                    ->ignore($productId)
                    ->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at')),
            ],
            'description' => ['nullable', 'string'],
            'type' => [
                'sometimes',
                'required',
                'string',
                Rule::in(['storable', 'raw_material', 'composite', 'service']),
            ],
            'inventory_policy' => [
                'sometimes',
                'required',
                'string',
                Rule::in(['direct_deduction', 'auto_deduct_bom_on_sale', 'production_order_required']),
            ],
            'tracking_type' => [
                'sometimes',
                'required',
                'string',
                Rule::in(['none', 'by_batch', 'by_serial']),
            ],
            'valuation_method' => [
                'sometimes',
                'required',
                'string',
                Rule::in(['avco', 'fifo', 'standard']),
            ],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],

            // قواعد التحقق للوحدات المربوطة
            'units' => ['sometimes', 'required', 'array', 'min:1'],
            'units.*.unit_id' => [
                'required_with:units',
                'integer',
                Rule::exists('inventory_units', 'id')->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at')),
            ],
            'units.*.conversion_factor' => ['required_with:units', 'numeric', 'gt:0'],
            'units.*.is_base_unit' => ['required_with:units', 'boolean'],
            'units.*.is_purchase_unit' => ['sometimes', 'boolean'],
            'units.*.is_sale_unit' => ['sometimes', 'boolean'],

            // قواعد التحقق للأسعار المرتبطة بالوحدات
            'units.*.prices' => ['nullable', 'array'],
            'units.*.prices.*.price_list_id' => [
                'required_with:units.*.prices',
                'integer',
                Rule::exists('inventory_price_lists', 'id')->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at')),
            ],
            'units.*.prices.*.price' => ['required_with:units.*.prices', 'numeric', 'min:0'],
            'units.*.prices.*.min_quantity' => ['nullable', 'numeric', 'min:0'],

            // قواعد التحقق للباركودات المرتبطة بالوحدات
            'units.*.barcodes' => ['nullable', 'array'],
            'units.*.barcodes.*' => [
                'nullable',
                'string',
                'max:100',
                'distinct',
                Rule::unique('inventory_product_barcodes', 'barcode')
                    ->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at'))
                    ->whereNotIn('product_unit_id', function ($query) use ($productId) {
                        $query->select('id')
                            ->from('inventory_product_units')
                            ->where('product_id', $productId);
                    }),
            ],

            // قواعد التحقق لقواعد إعادة الطلب المربوطة بالمستودعات
            'reorder_rules' => ['nullable', 'array'],
            'reorder_rules.*.warehouse_id' => [
                'required_with:reorder_rules',
                'integer',
                'distinct',
                Rule::exists('inventory_warehouses', 'id')->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at')),
            ],
            'reorder_rules.*.min_quantity' => ['required_with:reorder_rules', 'numeric', 'min:0'],
            'reorder_rules.*.max_quantity' => ['required_with:reorder_rules', 'numeric', 'gte:reorder_rules.*.min_quantity'],
            'reorder_rules.*.reorder_quantity' => ['required_with:reorder_rules', 'numeric', 'min:0'],
            'reorder_rules.*.is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * مصفوفة الرسائل المخصصة للتحقق
     */
    public function messages(): array
    {
        return [
            'category_id.exists' => 'التصنيف المحدد غير موجود أو لا ينتمي لهذه الشركة.',
            'name.required' => 'اسم المنتج مطلوب.',
            'sku.unique' => 'رمز SKU مستخدم من قبل في هذه الشركة.',
            'type.in' => 'نوع المنتج المحدد غير صالح.',
            'inventory_policy.in' => 'سياسة المخزون غير صالحة.',
            'tracking_type.in' => 'سياسة التتبع غير صالحة.',
            'valuation_method.in' => 'طريقة التقييم غير صالحة.',
            'units.required' => 'يجب إضافة وحدة قياس واحدة على الأقل للمنتج.',
            'units.min' => 'يجب إضافة وحدة قياس واحدة على الأقل للمنتج.',
            'units.*.unit_id.exists' => 'وحدة القياس المحددة غير موجودة في هذه الشركة.',
            'units.*.conversion_factor.gt' => 'معامل التحويل يجب أن يكون أكبر من الصفر.',
            'units.*.barcodes.*.unique' => 'الباركود محجوز مسبقاً لمنتج آخر في هذه الشركة.',
            'reorder_rules.*.warehouse_id.required_with' => 'المستودع مطلوب لقاعدة إعادة الطلب.',
            'reorder_rules.*.warehouse_id.exists' => 'المستودع المحدد لقاعدة إعادة الطلب غير موجود في هذه الشركة.',
            'reorder_rules.*.warehouse_id.distinct' => 'لا يمكن تكرار المستودع نفسه أكثر من مرة في قواعد إعادة الطلب.',
            'reorder_rules.*.min_quantity.required_with' => 'الحد الأدنى (حد الأمان) مطلوب.',
            'reorder_rules.*.max_quantity.gte' => 'الحد الأقصى يجب أن يكون أكبر من أو يساوي الحد الأدنى (حد الأمان).',
            'reorder_rules.*.reorder_quantity.required_with' => 'الكمية الموصى بشرائها مطلوبة.',
        ];
    }

    /**
     * قيود تحقق إضافية متقدمة على مستوى الوحدات
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (!$this->has('units')) {
                return;
            }

            $units = $this->input('units', []);
            if (!is_array($units)) {
                return;
            }

            $baseUnits = array_filter($units, fn ($unit) => !empty($unit['is_base_unit']));

            if (count($baseUnits) !== 1) {
                $validator->errors()->add('units', 'يجب تحديد وحدة أساسية واحدة فقط للصنف.');
                return;
            }

            $baseUnit = reset($baseUnits);
            if (isset($baseUnit['conversion_factor']) && (float) $baseUnit['conversion_factor'] !== 1.0) {
                $validator->errors()->add('units', 'معامل التحويل للوحدة الأساسية يجب أن يكون مساوياً لـ 1.');
            }
        });
    }
}