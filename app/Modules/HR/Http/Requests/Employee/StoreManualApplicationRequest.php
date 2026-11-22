<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManualApplicationRequest extends FormRequest
{
    /**
     * التحقق من امتلاك الموظف لصلاحية الإدخال اليدوي لطلبات التدريب والتوظيف
     */
    public function authorize(): bool
    {
        return $this->user()->hasPermissionTo('hr.internship_applications.create');
    }

    /**
     * قوانين التحقق للإدخال اليدوي المحلي من لوحة الإدارة
     */
    public function rules(): array
    {
        return [
            'application_type' => ['required', Rule::in(['internship', 'employment'])],
            'full_name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],

            'phone' => [
                'required',
                'string',
                'min:10',
                'max:50',
                Rule::unique('hr_internship_applications', 'phone')->where(function ($query) {
                    return $query->whereIn('status', ['pending', 'interview', 'approved']);
                })
            ],

            'national_id' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('hr_internship_applications', 'national_id')->where(function ($query) {
                    return $query->whereIn('status', ['pending', 'interview', 'approved']);
                })
            ],

            // حقول التدريب الأكاديمي
            'academic_institution' => ['nullable', 'string', 'max:255'],
            'academic_major' => ['nullable', 'string', 'max:255'],
            'required_training_hours' => ['nullable', 'integer', 'min:1', 'max:999'],

            // حقول التوظيف
            'position_id' => ['nullable', 'exists:positions,id'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'experience_years' => ['nullable', 'integer', 'min:0', 'max:60'],

            // المرفقات اختيارية في الإدخال اليدوي المحلي
            'photo' => [
                'nullable',
                'image',
                'mimes:jpeg,png,jpg',
                'max:3072'
            ],
            'cv' => [
                'nullable',
                'file',
                'mimes:pdf,doc,docx,jpeg,png,jpg',
                'max:5120'
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * رسائل الخطأ المخصصة باللغة العربية
     */
    public function messages(): array
    {
        return [
            'application_type.required' => 'يجب تحديد نوع الطلب (تدريب أم توظيف).',
            'application_type.in' => 'نوع الطلب المحدد غير صالح.',
            'full_name.required' => 'الاسم الكامل مطلوب.',
            'full_name.min' => 'يجب ألا يقل الاسم عن 3 أحرف.',
            'phone.required' => 'رقم الهاتف مطلوب.',
            'phone.unique' => 'يوجد طلب مسجل بالفعل بهذا الرقم وهو قيد المراجعة أو المعاينة أو مقبول حالياً.',
            'national_id.unique' => 'رقم الهوية/الإقامة هذا مسجل به طلب قائم بالفعل.',
            'position_id.exists' => 'المسمى الوظيفي المحدد غير موجود.',
            'experience_years.integer' => 'سنوات الخبرة يجب أن تكون رقماً صحيحاً.',
            'photo.image' => 'الملف المرسل يجب أن يكون صورة حقيقية فقط.',
            'photo.mimes' => 'صيغة الصورة يجب أن تكون حصراً: jpeg, png, jpg.',
            'photo.max' => 'حجم الصورة الشخصية كبير جداً، الحد الأقصى المسموح به هو 3 ميجابايت.',
            'cv.file' => 'ملف السيرة الذاتية غير صالح.',
            'cv.mimes' => 'صيغة السيرة الذاتية يجب أن تكون: pdf, doc, docx, jpeg, png, jpg.',
            'cv.max' => 'حجم السيرة الذاتية تجاوز الحد الأقصى المسموح به وهو 5 ميجابايت.',
            'notes.max' => 'حقل الملاحظات تجاوز الحد الأقصى المسموح به وهو 2000 حرف.',
        ];
    }
}