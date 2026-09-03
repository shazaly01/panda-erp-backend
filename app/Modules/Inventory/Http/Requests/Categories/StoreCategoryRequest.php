<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests\Categories;

use App\Modules\Inventory\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    /**
     * تحديد ما إذا كان المستخدم مصرحاً له بتنفيذ هذا الطلب
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Category::class);
    }

    /**
     * قواعد التحقق الخاصة بإنشاء تصنيف جديد
     */
    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'code' => ['nullable', 'string', 'max:50', Rule::unique('inventory_categories', 'code')->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at'))],
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