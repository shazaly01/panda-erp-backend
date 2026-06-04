<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\LeavePass;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeavePassRequest extends FormRequest
{
    /**
     * التخويل تدار صلاحياته عبر طبقة الـ Policy الصارمة
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * قواعد التحقق عند تعديل بيانات الإذن المؤقت
     */
    public function rules(): array
    {
        return [
            'date'                => 'sometimes|required|date_format:Y-m-d',
            'reason'              => 'sometimes|required|string|max:255',
            'requested_leave_at'  => 'sometimes|required|date_format:H:i',
            // التحقق من التوقيت المنطقي؛ يجب قراءة حقل الخروج الحالي أو الممرر حديثاً
            'requested_return_at' => 'sometimes|required|date_format:H:i|after:' . ($this->requested_leave_at ?? $this->route('leave_pass')->requested_leave_at),
        ];
    }

    /**
     * رسائل التنبيه والتدقيق باللغة العربية
     */
    public function messages(): array
    {
        return [
            'date.date_format'             => 'صيغة التاريخ الممررة غير صحيحة، يجب أن تكون Y-m-d.',
            'reason.required'              => 'سبب الخروج المؤقت حقل مطلوب ولا يمكن تركه فارغاً.',
            'requested_leave_at.date_format'  => 'صيغة وقت الخروج غير مطابقة للمعايير H:i.',
            'requested_return_at.date_format' => 'صيغة وقت العودة غير مطابقة للمعايير H:i.',
            'requested_return_at.after'    => 'خطأ في الجدولة: وقت العودة للمنشأة يجب أن يكون لاحقاً لوقت المغادرة.',
        ];
    }
}
