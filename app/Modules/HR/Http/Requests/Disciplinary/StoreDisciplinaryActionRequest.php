<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\Disciplinary;

use Illuminate\Foundation\Http\FormRequest;

class StoreDisciplinaryActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id'       => ['required', 'integer', 'exists:employees,id'],
            'violation_type_id' => ['required', 'integer', 'exists:hr_violation_types,id'],
            'violation_date'    => ['required', 'date'],
            'reason'            => ['nullable', 'string', 'max:1000'],
            'auto_approve'      => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required'       => 'يجب تحديد الموظف المعني بالمخالفة.',
            'employee_id.exists'         => 'الموظف المحدد غير موجود.',
            'violation_type_id.required' => 'يجب تحديد نوع المخالفة أو اللائحة.',
            'violation_type_id.exists'   => 'نوع المخالفة المحدد غير موجود.',
            'violation_date.required'    => 'تاريخ وقوع المخالفة مطلوب.',
            'violation_date.date'        => 'صيغة تاريخ المخالفة غير صحيحة.',
        ];
    }
}