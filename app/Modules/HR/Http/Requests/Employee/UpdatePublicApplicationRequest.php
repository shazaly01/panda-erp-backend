<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\Employee;

use App\Modules\HR\Models\InternshipApplication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePublicApplicationRequest extends FormRequest
{
    /**
     * السماح للمتقدم بتحديث بياناته (يتم التحقق من حالة الطلب داخل الخدمة)
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * قوانين التحقق الكاملة أثناء التحديث مع التوافق التام لطلبات التدريب والتوظيف
     */
    public function rules(): array
    {
        $applicationId = $this->route('id');
        $application = $applicationId ? InternshipApplication::find($applicationId) : null;
        $isInternship = ($application?->application_type ?? 'internship') === 'internship';

        return [
            'full_name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => [
                'required',
                'string',
                'min:10',
                'max:50',
                Rule::unique('hr_internship_applications', 'phone')
                    ->ignore($applicationId)
                    ->where(function ($query) {
                        return $query->whereIn('status', ['pending', 'interview', 'approved']);
                    }),
            ],
            'national_id' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('hr_internship_applications', 'national_id')
                    ->ignore($applicationId)
                    ->where(function ($query) {
                        return $query->whereIn('status', ['pending', 'interview', 'approved']);
                    }),
            ],

            // حقول التدريب الأكاديمي (إلزامية للتدريب واختيارية للتوظيف)
            'academic_institution' => [Rule::requiredIf($isInternship), 'nullable', 'string', 'max:255'],
            'academic_major' => [Rule::requiredIf($isInternship), 'nullable', 'string', 'max:255'],
            'required_training_hours' => ['nullable', 'integer', 'min:1', 'max:999'],

            // حقول التوظيف الاختيارية
            'position_id' => ['nullable', 'exists:positions,id'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'experience_years' => ['nullable', 'integer', 'min:0', 'max:60'],

            'photo' => [
                'nullable',
                'image',
                'mimes:jpeg,png,jpg',
                'max:3072',
            ],
            'cv' => [
                'nullable',
                'file',
                'mimes:pdf,doc,docx,jpeg,png,jpg',
                'max:5120',
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
            'phone.unique' => 'يوجد طلب مسجل بالفعل بهذا الرقم وهو قيد المراجعة أو المعاينة أو مقبول حالياً.',
            'national_id.unique' => 'رقم الهوية/الإقامة هذا مسجل به طلب قائم بالفعل.',
            'academic_institution.required' => 'اسم الجامعة أو المؤسسة الأكاديمية مطلوب.',
            'academic_major.required' => 'التخصص الدراسي مطلوب.',
            'position_id.exists' => 'المسمى الوظيفي المحدد غير موجود.',
            'experience_years.integer' => 'سنوات الخبرة يجب أن تكون رقماً صحيحاً.',
            'photo.image' => 'الملف المرسل يجب أن يكون صورة حقيقية فقط.',
            'photo.mimes' => 'صيغة الصورة يجب أن تكون حصراً: jpeg, png, jpg.',
            'photo.max' => 'حجم الصورة الشخصية كبير جداً، الحد الأقصى هو 3 ميجابايت.',
            'cv.file' => 'ملف السيرة الذاتية غير صالح.',
            'cv.mimes' => 'صيغة السيرة الذاتية يجب أن تكون: pdf, doc, docx, jpeg, png, jpg.',
            'cv.max' => 'حجم السيرة الذاتية تجاوز الحد الأقصى المسموح به وهو 5 ميجابايت.',
            'notes.max' => 'حقل الملاحظات تجاوز الحد الأقصى المسموح به وهو 2000 حرف.',
        ];
    }
}