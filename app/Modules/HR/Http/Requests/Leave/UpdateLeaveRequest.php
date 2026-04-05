<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\Leave;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // الصلاحيات (هل الطلب لا يزال معلقاً؟) تمت معالجتها في LeaveRequestPolicy
    }

    public function rules(): array
    {
        return [
            // استخدمنا sometimes لكي لا نجبر الواجهة الأمامية على إرسال كل الحقول في حالة التعديل الجزئي
            'leave_type_id' => ['sometimes', 'required', 'exists:hr_leave_types,id'],
            'start_date'    => ['sometimes', 'required', 'date'],
            'end_date'      => ['sometimes', 'required', 'date', 'after_or_equal:start_date'],
            'reason'        => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'تاريخ نهاية الإجازة يجب أن يكون مساوياً أو بعد تاريخ البداية.',
        ];
    }
}
