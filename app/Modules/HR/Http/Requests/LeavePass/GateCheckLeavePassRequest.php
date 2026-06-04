<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\LeavePass;

use Illuminate\Foundation\Http\FormRequest;

class GateCheckLeavePassRequest extends FormRequest
{
    /**
     * التخويل تدار صلاحياته عبر طبقة الـ Policy
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * قواعد توثيق الحركة اللحظية للأمن والسلامة
     */
    public function rules(): array
    {
        return [
            // حصر الإجراءات المتاحة للحارس في حركتين فقط
            // check_out: إثبات مغادرة أسوار المنشأة | check_in: إثبات العودة ودخول الأسوار
            'action' => 'required|string|in:check_out,check_in',
        ];
    }

    /**
     * رسائل التدقيق المخصصة لجهاز الأمن
     */
    public function messages(): array
    {
        return [
            'action.required' => 'يجب تحديد نوع الحركة الأمنية المطلوبة (إثبات خروج أو إثبات عودة).',
            'action.in'       => 'الإجراء الأمني الممرر غير معرّف بنظام التحكم بالوصول الموحد.',
        ];
    }
}
