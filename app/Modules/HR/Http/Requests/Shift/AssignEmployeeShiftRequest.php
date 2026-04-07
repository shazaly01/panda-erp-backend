<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\Shift;

use Illuminate\Foundation\Http\FormRequest;

class AssignEmployeeShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'shift_id' => ['required', 'exists:hr_shifts,id'],

            'start_date' => ['required', 'date'],
            // نهاية الوردية اختيارية (قد تكون وردية دائمة)، وإذا وُجدت يجب أن تكون بعد البداية
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],

            // حقل العطلات الأسبوعية يجب أن يكون مصفوفة
            'weekend_days' => ['nullable', 'array'],
            // التحقق من أن القيم داخل المصفوفة هي أيام أسبوع صحيحة بالإنجليزية (أو أرقام 0-6 حسب ما تفضله في الداتا بيز)
            // هنا افترضنا استخدام الأسماء الإنجليزية لتسهيل استخدامها مع Carbon
            'weekend_days.*' => ['string', 'in:Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday'],
        ];
    }

    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'تاريخ انتهاء الوردية يجب أن يكون بعد تاريخ البدء.',
            'weekend_days.*.in' => 'أيام العطلة المحددة غير صالحة.',
        ];
    }
}
