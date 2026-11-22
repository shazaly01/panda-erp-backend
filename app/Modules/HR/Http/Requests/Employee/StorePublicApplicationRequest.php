<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Exceptions\HttpResponseException;

class StorePublicApplicationRequest extends FormRequest
{
    /**
     * تهيئة القيم الافتراضية قبل التحقق لضمان التوافق العكسي الكامل مع واجهة التدريب القائمة
     */
    protected function prepareForValidation(): void
    {
        if (!$this->filled('application_type')) {
            $this->merge([
                'application_type' => 'internship',
            ]);
        }
    }

    /**
     * التحقق مما إذا كان التقديم مفتوحاً حسب نوع الطلب (تدريب أو توظيف)
     */
    public function authorize(): bool
    {
        $type = $this->input('application_type', 'internship');

        if ($type === 'employment') {
            return (bool) Cache::get('hr_job_applications_open', true);
        }

        return (bool) Cache::get('hr_internship_applications_open', true);
    }

    /**
     * إرجاع استجابة مخصصة باللغة العربية عند محاولة التقديم والطلب مغلق
     */
    protected function failedAuthorization()
    {
        $type = $this->input('application_type', 'internship');
        $message = $type === 'employment'
            ? 'عذراً، استقبال طلبات التوظيف مغلق حالياً.'
            : 'عذراً، استقبال طلبات التدريب مغلق حالياً.';

        throw new HttpResponseException(response()->json([
            'message' => $message
        ], 403));
    }

    /**
     * القوانين الصارمة للتحقق من المدخلات وحماية السيرفر من الملفات الخبيثة والثغرات
     */
    public function rules(): array
    {
        $isInternship = $this->input('application_type', 'internship') === 'internship';

        return [
            'application_type' => ['required', Rule::in(['internship', 'employment'])],
            'full_name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],

            // 🛡️ قفل الحماية: منع التكرار برقم الهاتف إذا كان للعميل طلب معلق أو قيد المعاينة أو مقبول
            'phone' => [
                'required',
                'string',
                'min:10',
                'max:50',
                Rule::unique('hr_internship_applications', 'phone')->where(function ($query) {
                    return $query->whereIn('status', ['pending', 'interview', 'approved']);
                })
            ],

            // 🛡️ تأمين إضافي بالهوية الوطنية لضمان عدم التلاعب برقم هاتف آخر
            'national_id' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('hr_internship_applications', 'national_id')->where(function ($query) {
                    return $query->whereIn('status', ['pending', 'interview', 'approved']);
                })
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
                Rule::requiredIf($isInternship),
                'nullable',
                'image',
                'mimes:jpeg,png,jpg',
                'max:3072' // الحد الأقصى 3 ميجابايت لحماية مساحة خادم Contabo
            ],
            'cv' => [
                'nullable',
                'file',
                'mimes:pdf,doc,docx,jpeg,png,jpg',
                'max:5120' // الحد الأقصى 5 ميجابايت للسيرة الذاتية
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
            'application_type.in' => 'نوع الطلب المحدد غير صالح.',
            'full_name.required' => 'الاسم الكامل مطلوب.',
            'full_name.min' => 'يجب ألا يقل الاسم عن 3 أحرف.',
            'phone.required' => 'رقم الهاتف مطلوب.',
            'phone.unique' => 'يوجد طلب مسجل بالفعل بهذا الرقم وهو قيد المراجعة أو المعاينة أو مقبول حالياً.',
            'national_id.unique' => 'رقم الهوية/الإقامة هذا مسجل به طلب قائم بالفعل.',
            'academic_institution.required' => 'اسم الجامعة أو المؤسسة الأكاديمية مطلوب.',
            'academic_major.required' => 'التخصص الدراسي مطلوب.',
            'position_id.exists' => 'المسمى الوظيفي المحدد غير موجود.',
            'experience_years.integer' => 'سنوات الخبرة يجب أن تكون رقماً صحيحاً.',
            'photo.required' => 'التقاط الصورة الشخصية عبر الكاميرا أمر إلزامي لاتمام الطلب.',
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