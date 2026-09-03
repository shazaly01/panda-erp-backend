<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests\Categories;

use App\Modules\Inventory\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    /**
     * تحديد ما إذا كان المستخدم مصرحاً له بتنفيذ هذا الطلب
     */
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $this->user()->can('update', $category);
    }

    /**
     * قواعد التحقق الخاصة بتحديث التصنيف
     */
    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        $category = $this->route('category');
        $categoryId = $category?->id ?? $category;

        return [
            'code' => ['nullable', 'string', 'max:50', Rule::unique('inventory_categories', 'code')->ignore($categoryId)->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at'))],
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', Rule::exists('inventory_categories', 'id')->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at'))],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * مصفوفة الرسائل المخصصة للتحقق
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'رمز التصنيف مستخدم من قبل في هذه الشركة.',
            'name.required' => 'اسم التصنيف مطلوب.',
            'parent_id.exists' => 'التصنيف الأب المحدد غير موجود.',
        ];
    }
}