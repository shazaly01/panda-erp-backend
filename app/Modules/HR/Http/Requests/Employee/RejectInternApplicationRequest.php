<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class RejectInternApplicationRequest extends FormRequest
{
    /**
     * التحقق من امتلاك المستخدم لصلاحية الشاشة الموحدة قبل إتمام عملية الرفض
     */
    public function authorize(): bool
    {
        return $this->user()->hasPermissionTo('hr.internship_applications.view');
    }

    /**
     * القوانين الخاصة بالرفض (فارغة لعدم وجود مدخلات نصية إضافية مطلوب فحصها من المستخدم)
     */
    public function rules(): array
    {
        return [
            // لا توجد حقول مطلوبة للرفض المباشر
        ];
    }

    /**
     * رسائل الخطأ المخصصة
     */
    public function messages(): array
    {
        return [];
    }
}
