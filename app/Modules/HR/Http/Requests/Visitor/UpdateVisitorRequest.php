<?php

namespace App\Modules\HR\Http\Requests\Visitor;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVisitorRequest extends FormRequest
{
    /**
     * تحديد ما إذا كان المستخدم مخولاً لإجراء هذا الطلب.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * قواعد التحقق المطبقة على المدخلات عند التعديل.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'max:20'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'company_from' => ['nullable', 'string', 'max:255'],
            'purpose' => ['nullable', 'string'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'status' => ['sometimes', 'required', 'string', 'in:pending,approved,checked_in,checked_out,canceled'],
        ];
    }
}
