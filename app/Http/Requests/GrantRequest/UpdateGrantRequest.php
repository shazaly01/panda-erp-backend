<?php

declare(strict_types=1);

namespace App\Http\Requests\GrantRequest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGrantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $grantRequestId = $this->route('grant_request')?->id ?? $this->route('grant_request');

        return [
            'request_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('grant_requests', 'request_number')->ignore($grantRequestId),
            ],
            'target_organization' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'request_date' => ['required', 'date'],
            'status' => ['nullable', 'string', Rule::in(['draft', 'submitted', 'under_review', 'partially_approved', 'approved', 'rejected', 'completed'])],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer', 'exists:grant_request_items,id'],
            'items.*.department_id' => ['required', 'integer', 'exists:departments,id'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.specifications' => ['nullable', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'request_number' => 'رقم الطلب',
            'target_organization' => 'الجهة المستهدفة',
            'title' => 'عنوان الطلب',
            'request_date' => 'تاريخ الطلب',
            'status' => 'حالة الطلب',
            'notes' => 'الملاحظات',
            'items' => 'بنود الطلب',
            'items.*.id' => 'معرف البند',
            'items.*.department_id' => 'القسم',
            'items.*.item_name' => 'اسم البند',
            'items.*.specifications' => 'المواصفات',
            'items.*.quantity' => 'الكمية',
            'items.*.unit' => 'الوحدة',
            'items.*.estimated_cost' => 'التكلفة التقديرية',
            'items.*.notes' => 'ملاحظات البند',
        ];
    }
}