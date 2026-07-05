<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePublicApplicationRequest extends FormRequest
{
    /**
     * السماح لجميع المتقدمين الخارجيين بالوصول للرابط بدون تسجيل دخول
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * القوانين الصارمة للتحقق من المدخلات وحماية السيرفر من الملفات الخبيثة والثغرات
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],

            // 🛡️ قفل الحماية: منع التكرار برقم الهاتف إذا كان للعميل طلب معلق أو مقبول
            'phone' => [
                'required',
                'string',
                'min:10',
                'max:50',
                Rule::unique('hr_internship_applications', 'phone')->where(function ($query) {
                    return $query->whereIn('status', ['pending', 'approved']);
                })
            ],

            // 🛡️ تأمين إضافي بالهوية الوطنية لضمان عدم التلاعب برقم هاتف آخر
            'national_id' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('hr_internship_applications', 'national_id')->where(function ($query) {
                    return $query->whereIn('status', ['pending', 'approved']);
                })
            ],

            'academic_institution' => ['required', 'string', 'max:255'],
            'academic_major' => ['required', 'string', 'max:255'],
            'required_training_hours' => ['nullable', 'integer', 'min:1', 'max:999'],
            'photo' => [
                'required',
                'image',
                'mimes:jpeg,png,jpg',
                'max:3072' // الحد الأقصى 3 ميجابايت لحماية مساحة خادم Contabo
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * رسائل الخطأ المخصصة باللغة العربية للواجهة الأمامية
     */
    public function messages(): array
    {
        return [
            'full_name.required' => 'الاسم الكامل مطلوب.',
            'full_name.min' => 'يجب ألا يقل الاسم عن 3 أحرف.',
            'phone.required' => 'رقم الهاتف مطلوب.',
            'phone.unique' => 'يوجد طلب تدريب مسجل بالفعل بهذا الرقم وهو قيد المراجعة أو مقبول حالياً.',
            'national_id.unique' => 'رقم الهوية/الإقامة هذا مسجل به طلب تدريب قائم بالفعل.',
            'academic_institution.required' => 'اسم الجامعة أو المؤسسة الأكاديمية مطلوب.',
            'academic_major.required' => 'التخصص الدراسي مطلوب.',
            'photo.required' => 'التقاط الصورة الشخصية عبر الكاميرا أمر إلزامي لاتمام الطلب.',
            'photo.image' => 'الملف المرسل يجب أن يكون صورة حقيقية فقط.',
            'photo.mimes' => 'صيغة الصورة يجب أن تكون حصراً: jpeg, png, jpg.',
            'photo.max' => 'حجم الصورة الشخصية كبير جداً، الحد الأقصى المسموح به هو 3 ميجابايت.',
            'notes.max' => 'حقل الملاحظات تجاوز الحد الأقصى المسموح به وهو 2000 حرف.',
        ];
    }
}
