<?php

namespace App\Modules\HR\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Modules\HR\Enums\Gender;
use App\Modules\HR\Enums\MaritalStatus;
use App\Modules\HR\Enums\EmploymentType;
use App\Modules\HR\Enums\EmployeeStatus;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // نرجع true لأن الـ EmployeePolicy سيتولى عملية التحقق في المتحكم
        return true;
    }

    public function rules(): array
    {
        // جلب معرف الموظف الحالي من الرابط (Route Model Binding)
        $employeeId = $this->route('employee');

        return [
            'full_name' => ['required', 'string', 'max:200'],

            // 🌟 لاحظ هنا استخدام ignore() لتخطي الموظف الحالي من الفحص
            'national_id' => [
                'nullable',
                'numeric',
                Rule::unique('employees', 'national_id')->ignore($employeeId)
            ],

            'email' => [
                'nullable',
                'email',
                Rule::unique('employees', 'email')->ignore($employeeId)
            ],
// جعلناه nullable ليتوافق مع سياسة التوليد
            'employee_number' => [
                'nullable',
                'string',
                Rule::unique('employees', 'employee_number')->ignore($employeeId)
            ],

            // 🌟 إضافة حقل الباركود الجديد مع استثناء الموظف الحالي من الفحص
            'barcode' => [
                'nullable',
                'string',
                Rule::unique('employees', 'barcode')->ignore($employeeId)
            ],

            'phone' => ['nullable', 'string', 'max:20'],
            'join_date' => ['required', 'date'],

            // التحقق من القوائم (Enums)
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'marital_status' => ['nullable', Rule::enum(MaritalStatus::class)],
            'employment_type' => ['required', Rule::enum(EmploymentType::class)],

            // إضافة حالة الموظف لأن التعديل غالباً يتضمن تغيير الحالة (استقال، إجازة...)
            'status' => ['required', Rule::enum(EmployeeStatus::class)],

            // التحقق من العلاقات
            'department_id' => ['nullable', 'exists:departments,id'],
            'position_id' => ['nullable', 'exists:positions,id'],

            // لا يمكن للموظف أن يكون مديراً لنفسه!
            'manager_id' => [
                'nullable',
                'exists:employees,id',
                // منع اختيار الموظف كمدير لنفسه
                Rule::notIn([$employeeId instanceof \Illuminate\Database\Eloquent\Model ? $employeeId->id : $employeeId])
            ],

            'user_id' => ['nullable', 'exists:users,id'],
        ];
    }
}
