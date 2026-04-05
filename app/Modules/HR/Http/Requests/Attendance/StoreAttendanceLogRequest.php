<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // الصلاحيات تدار عبر الـ Policies
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'date' => ['required', 'date', 'before_or_equal:today'], // لا يمكن تسجيل حضور ليوم في المستقبل

            // وقت الحضور (صيغة 24 ساعة كمثال: 08:30)
            'check_in' => ['nullable', 'date_format:H:i'],

            // وقت الانصراف يجب أن يكون منطقياً (إذا وجد)
            'check_out' => ['nullable', 'date_format:H:i', 'after:check_in'],

            'status' => ['required', 'in:present,absent,late,on_leave'],
        ];
    }

    public function messages(): array
    {
        return [
            'check_out.after' => 'وقت الانصراف يجب أن يكون بعد وقت الحضور.',
            'date.before_or_equal' => 'لا يمكنك تسجيل حضور لتاريخ في المستقبل.',
        ];
    }
}
