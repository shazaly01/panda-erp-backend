<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Modules\Accounting\Models\CostCenter;
// use App\Modules\Accounting\Enums\CostCenterType; // تم الإبقاء عليه إن كنت تحتاجه لاحقاً

class StoreCostCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CostCenter::class);
    }

    public function rules(): array
    {
        return [
            // 🚫 تمت إزالة حقل 'code' بالكامل لتفعيل الإنشاء الهرمي الآلي (Auto-generation)

            'name'        => ['required', 'string', 'max:150'],
            'parent_id'   => ['nullable', 'integer', 'exists:cost_centers,id'],
            'is_active'   => ['boolean'],
            'notes'       => ['nullable', 'string', 'max:255'],
            'is_branch'   => ['boolean'],
            'code_prefix' => ['nullable', 'string', 'max:10'],
        ];
    }
}
