<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\LeavePass;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeavePassRequest extends FormRequest
{
    /**
     * تحديد ما إذا كان المستخدم مخولاً برفع هذا الطلب (يتم التحكم بها عبر الـ Policy)
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * القواعد الصارمة للتحقق من مدخلات الإذن المؤقت
     */
    public function rules(): array
    {
        return [
            'employee_id'         => 'required|integer|exists:employees,id',
            'date'                => 'required|date_format:Y-m-d',
            'reason'              => 'required|string|max:255',
            // التحقق من صيغة الوقت (ساعة:دقيقة) لمنع التشوهات في قاعدة البيانات
            'requested_leave_at'  => 'required|date_format:H:i',
            // شرط معماري: يجب أن يكون وقت العودة المتوقع لاحقاً لوقت الخروج المؤقت
            'requested_return_at' => 'required|date_format:H:i|after:requested_leave_at',
        ];
    }

    /**
     * تخصيص رسائل الخطأ لتظهر بلغة عربية واضحة للمستخدم
     */
    public function messages(): array
    {
        return [
            'employee_id.required'         => 'يجب تحديد الموظف طالب الإذن.',
            'employee_id.exists'           => 'الموظف المحدد غير مسجل بنظام الموارد البشرية.',
            'date.required'                => 'حقل التاريخ إجباري.',
            'date.date_format'             => 'صيغة التاريخ يجب أن تكون Y-m-d.',
            'reason.required'              => 'يرجى كتابة سبب الخروج المؤقت من المنشأة.',
            'requested_leave_at.required'  => 'تحديد وقت الخروج المتوقع مطلوب.',
            'requested_return_at.required' => 'تحديد وقت العودة المتوقع مطلوب.',
            'requested_return_at.after'    => 'خطأ منطقي: وقت العودة يجب أن يكون بعد وقت الخروج.',
        ];
    }
}
