<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAttendanceLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // الحماية المسبقة موجودة في AttendanceLogPolicy
    }

    public function rules(): array
    {
        return [
            // نستخدم sometimes للتحديث الجزئي
            'check_in'  => ['nullable', 'date_format:H:i'],

            // في حال تم إرسال check_in و check_out معاً، يجب أن يكون الانصراف بعد الحضور
            'check_out' => ['nullable', 'date_format:H:i', 'after:check_in'],

            'status'    => ['sometimes', 'required', 'in:present,absent,late,on_leave'],
        ];
    }

    public function messages(): array
    {
        return [
            'check_out.after' => 'وقت الانصراف يجب أن يكون بعد وقت الحضور.',
        ];
    }
}
