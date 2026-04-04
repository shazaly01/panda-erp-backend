<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Modules\Accounting\Models\CostCenter;
use App\Modules\Accounting\Enums\CostCenterType;

class UpdateCostCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('cost_center'));
    }

    public function rules(): array
    {
        $id = $this->route('cost_center')->id;

        return [
            // استثناء الـ ID الحالي من فحص التكرار
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('cost_centers', 'code')->ignore($id)],

            'name' => ['sometimes', 'required', 'string', 'max:150'],
            // منع اختيار المركز نفسه كأب لنفسه
            'parent_id' => ['nullable', 'exists:cost_centers,id', Rule::notIn([$id])],

            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
            'is_branch' => ['boolean'],
            'code_prefix' => ['nullable', 'string', 'max:10'],
        ];
    }
}
