<?php

namespace App\Modules\HR\Http\Requests\Visitor;

use Illuminate\Foundation\Http\FormRequest;

class StoreVisitorRequest extends FormRequest
{
    /**
     * تحديد ما إذا كان المستخدم مخولاً لإجراء هذا الطلب.
     */
    public function authorize(): bool
    {
        // نُعيد true لأن التحقق من الصلاحيات الأمنية (Policies) سيتم إدارته
        // داخل موديول لوحة التحكم، وللسماح للرابط الخارجي العام بالوصول لهنا.
        return true;
    }

    /**
     * قواعد التحقق المطبقة على المدخلات.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'company_from' => ['nullable', 'string', 'max:255'],
            'purpose' => ['nullable', 'string'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
        ];
    }
}
