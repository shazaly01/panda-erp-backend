<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePublicApplicationRequest extends FormRequest
{
    /**
     * السماح للمتقدم بتحديث بياناته (سيتم التحقق من حالة الطلب داخل الـ Policy أو Service)
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * قوانين التحقق الكاملة أثناء التحديث (جعل الصورة اختيارية لتجنب إجبار المستخدم على إعادة التقاطها)
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['required', 'string', 'min:10', 'max:50'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'academic_institution' => ['required', 'string', 'max:255'],
            'academic_major' => ['required', 'string', 'max:255'],
            'required_training_hours' => ['nullable', 'integer', 'min:1', 'max:999'],
            'internship_start_date' => ['required', 'date'],
            'internship_end_date' => ['required', 'date', 'after:internship_start_date'],
            'photo' => [
                'nullable',
                'image',
                'mimes:jpeg,png,jpg',
                'max:3072'
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * رسائل الخطأ المخصصة للتحديث باللغة العربية
     */
    public function messages(): array
    {
        return [
            'full_name.required' => 'الاسم الكامل مطلوب.',
            'full_name.min' => 'يجب ألا يقل الاسم عن 3 أحرف.',
            'phone.required' => 'رقم الهاتف مطلوب.',
            'academic_institution.required' => 'اسم الجامعة أو المؤسسة الأكاديمية مطلوب.',
            'academic_major.required' => 'التخصص الدراسي مطلوب.',
            'internship_start_date.required' => 'تاريخ بدء التدريب مطلوب.',
            'internship_end_date.required' => 'تاريخ انتهاء التدريب مطلوب.',
            'internship_end_date.after' => 'تاريخ انتهاء التدريب يجب أن يكون بعد تاريخ البدء.',
            'photo.image' => 'الملف المرسل يجب أن يكون صورة حقيقية فقط.',
            'photo.mimes' => 'صيغة الصورة يجب أن تكون حصراً: jpeg, png, jpg.',
            'photo.max' => 'حجم الصورة الشخصية كبير جداً، الحد الأقصى هو 3 ميجابايت.',
            'notes.max' => 'حقل الملاحظات تجاوز الحد الأقصى المسموح به وهو 2000 حرف.',
        ];
    }
}
