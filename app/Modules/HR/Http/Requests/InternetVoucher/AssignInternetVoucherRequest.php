<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\InternetVoucher;

use Illuminate\Foundation\Http\FormRequest;

class AssignInternetVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        // يجب إضافة صلاحية 'internet_vouchers.assign' في نظام الصلاحيات لديك
        return $this->user()->hasPermissionTo('internet_vouchers.assign');
    }

    public function rules(): array
    {
        return [
            // التحقق من أن رقم الموظف موجود في قاعدة البيانات
            'employee_id' => ['required', 'numeric', 'exists:employees,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'يرجى تحديد الموظف المراد صرف الكود له.',
            'employee_id.numeric'  => 'معرف الموظف غير صالح.',
            'employee_id.exists'   => 'هذا الموظف غير مسجل في النظام.',
        ];
    }
}
