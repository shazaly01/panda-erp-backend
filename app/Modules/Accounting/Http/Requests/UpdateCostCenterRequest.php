<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Modules\Accounting\Models\CostCenter;

class UpdateCostCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        // استخدام route('cost_center') لجلب الكائن المراد تحديثه وفحص صلاحيته
        return $this->user()->can('update', $this->route('cost_center'));
    }

    public function rules(): array
    {
        $id = $this->route('cost_center')->id;

        return [
            // 🚫 تمت إزالة 'code' نهائياً لضمان ثبات الهوية الهرمية للمركز بعد إنشائه

            'name' => ['sometimes', 'required', 'string', 'max:150'],

            // منع اختيار المركز نفسه كأب لنفسه (حماية من الحلقات المفرغة Circular Reference)
            'parent_id' => [
                'nullable',
                'exists:cost_centers,id',
                Rule::notIn([$id])
            ],

            'is_active'   => ['boolean'],
            'notes'       => ['nullable', 'string', 'max:255'],
            'is_branch'   => ['boolean'],
            'code_prefix' => ['nullable', 'string', 'max:10'],
        ];
    }
}
