<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleInterviewRequest extends FormRequest
{
    /**
     * التحقق من امتلاك المستخدم لصلاحية جدولة وإدارة المعاينة
     */
    public function authorize(): bool
    {
        return $this->user()->hasPermissionTo('hr.internship_applications.interview');
    }

    /**
     * قوانين التحقق لبيانات المعاينة
     */
    public function rules(): array
    {
        return [
            'interview_date' => ['required', 'date'],
            'interview_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * رسائل الخطأ المخصصة باللغة العربية
     */
    public function messages(): array
    {
        return [
            'interview_date.required' => 'تاريخ ووقت المعاينة مطلوب.',
            'interview_date.date' => 'صيغة تاريخ المعاينة غير صحيحة.',
            'interview_notes.max' => 'ملاحظات المعاينة تجاوزت الحد الأقصى المسموح به وهو 2000 حرف.',
        ];
    }
}