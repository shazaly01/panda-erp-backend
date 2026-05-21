<?php

declare(strict_types=1);

namespace App\Http\Requests\Document;

use App\Enums\DocumentType;
use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Support\Facades\DB;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Document::class);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            // كفاءة عالمية: الحد الأقصى 50 ميجابايت للوثائق والفيديوهات والشهادات
            'file' => 'required|file|mimes:pdf,jpg,png,jpeg,docx,xlsx|max:51200',
            // نوع المستند الإجباري المعتمد على الـ Enum
            'document_type' => ['required', new Enum(DocumentType::class)],
            // تحديد الجهة المستهدفة داخل الـ ERP برمجياً
            'target_type' => 'required|in:company,project,employee,contract',
            'target_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) {
                    $type = $this->input('target_type');
                    $table = match($type) {
                        'company' => 'companies',
                        'project' => 'projects',
                        'employee' => 'employees',
                        'contract' => 'contracts',
                        default => null,
                    };

                    if ($table && !DB::table($table)->where('id', $value)->exists()) {
                        $targetNames = [
                            'company' => 'الشركة',
                            'project' => 'المشروع',
                            'employee' => 'الموظف',
                            'contract' => 'العقد'
                        ];
                        $fail("سجل {$targetNames[$type]} المختار غير موجود في النظام.");
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'حقل اسم المستند مطلوب لتسهيل الفهرسة.',
            'name.max' => 'اسم المستند طويل جداً ويؤثر على محركات البحث.',
            'file.required' => 'يرجى اختيار ملف الوثيقة لرفعها للأرشيف.',
            'file.mimes' => 'نوع الملف غير مدعوم أمنياً. الامتدادات المسموحة: PDF, JPG, PNG, JPEG, DOCX, XLSX.',
            'file.max' => 'حجم الملف يتجاوز الحد المسموح (50 ميغابايت). يرجى ضغطه والمحاولة مرة أخرى.',
            'file.uploaded' => 'فشل الرفع. الحجم يتجاوز إعدادات السيرفر المحرك لملف php.ini (upload_max_filesize).',
            'document_type.required' => 'تصنيف نوع المستند (عقد، فاتورة، صورة..) إجباري في الأنظمة العالمية.',
            'document_type.Illuminate\Validation\Rules\Enum' => 'نوع المستند المحدد غير مدرج في قائمة النظام المحمية.',
            'target_type.required' => 'يجب تحديد الموديول التابع له هذا الملف داخل الـ ERP.',
            'target_id.required' => 'معرف السجل المستهدف مطلوب لربط العلاقات برمجياً.',
        ];
    }
}
