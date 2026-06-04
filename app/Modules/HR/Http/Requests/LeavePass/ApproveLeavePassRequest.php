<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\LeavePass;

use Illuminate\Foundation\Http\FormRequest;

class ApproveLeavePassRequest extends FormRequest
{
    /**
     * التخويل تدار صلاحياته عبر طبقة الـ Policy
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * قواعد التحقق من قرار المشرف
     */
    public function rules(): array
    {
        return [
            // حصر القرار الإداري في حالتين فقط لا غير
            'status' => 'required|string|in:approved,rejected',
        ];
    }

    /**
     * تخصيص رسائل التدقيق
     */
    public function messages(): array
    {
        return [
            'status.required' => 'يجب تحديد القرار الإداري (اعتماد أو رفض).',
            'status.in'       => 'حالة الاعتماد الممررة غير معالجة بالنظام الموحد.',
        ];
    }
}
