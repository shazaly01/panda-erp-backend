<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests\PriceLists;

use App\Modules\Inventory\Models\PriceList;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePriceListRequest extends FormRequest
{
    /**
     * تحديد ما إذا كان المستخدم مصرحاً له بتنفيذ هذا الطلب
     */
    public function authorize(): bool
    {
        $priceList = $this->route('price_list') ?? $this->route('priceList');

        return $this->user()->can('update', $priceList);
    }

    /**
     * قواعد التحقق الخاصة بتحديث قائمة الأسعار
     */
    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        $priceList = $this->route('price_list') ?? $this->route('priceList');
        $priceListId = $priceList?->id ?? $priceList;

        return [
            'code' => ['nullable', 'string', 'max:50', Rule::unique('inventory_price_lists', 'code')->ignore($priceListId)->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at'))],
            'name' => ['required', 'string', 'max:255'],
            'currency_id' => ['required', 'integer', Rule::exists('currencies', 'id')->whereNull('deleted_at')],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'items' => ['nullable', 'array'],
            'items.*.product_id' => ['required_with:items', 'integer', Rule::exists('inventory_products', 'id')->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at'))],
            'items.*.product_unit_id' => ['required_with:items', 'integer', Rule::exists('inventory_product_units', 'id')->whereNull('deleted_at')],
            'items.*.price' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.min_quantity' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * مصفوفة الرسائل المخصصة للتحقق
     */
    public function messages(): array
    {
        return [
            'code.required' => 'رمز قائمة الأسعار مطلوب.',
            'code.unique' => 'رمز قائمة الأسعار مستخدم من قبل في هذه الشركة.',
            'name.required' => 'اسم قائمة الأسعار مطلوب.',
            'currency_id.required' => 'العملة مطلوبة.',
            'currency_id.exists' => 'العملة المحددة غير موجودة.',
            'items.*.product_id.required_with' => 'المنتج مطلوب لكل عنصر في القائمة.',
            'items.*.product_id.exists' => 'المنتج المحدد غير موجود.',
            'items.*.product_unit_id.required_with' => 'وحدة المنتج مطلوبة لكل عنصر في القائمة.',
            'items.*.product_unit_id.exists' => 'وحدة المنتج المحددة غير موجودة.',
            'items.*.price.required_with' => 'السعر مطلوب لكل عنصر في القائمة.',
            'items.*.price.min' => 'يجب ألا يقل السعر عن صفر.',
            'items.*.min_quantity.min' => 'يجب ألا يقل الحد الأدنى للكمية عن صفر.',
        ];
    }
}
