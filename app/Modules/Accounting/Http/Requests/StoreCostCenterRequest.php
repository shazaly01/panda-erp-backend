<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Modules\Accounting\Models\CostCenter;
use App\Modules\Accounting\Enums\CostCenterType;

class StoreCostCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CostCenter::class);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:cost_centers,code'],
            'name' => ['required', 'string', 'max:150'],
            'parent_id' => ['nullable', 'exists:cost_centers,id'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
            'is_branch' => ['boolean'],
            'code_prefix' => ['nullable', 'string', 'max:10'],
        ];
    }
}
