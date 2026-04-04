<?php

namespace App\Http\Requests\Document;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
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
            // 51200 KB = 50 MB
            'file' => 'required|file|mimes:pdf,jpg,png,mp4,mov|max:51200',
            'target_type' => 'required|in:company,project',
            'target_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) {
                    $type = $this->input('target_type');
                    $table = match($type) {
                        'company' => 'companies',
                        'project' => 'projects',
                        default => null,
                    };

                    if ($table && !DB::table($table)->where('id', $value)->exists()) {
                         // قمنا بتعريب الرسالة هنا أيضاً
                         $targetName = $type === 'company' ? 'الشركة' : 'المشروع';
                         $fail("سجل {$targetName} المختار غير موجود.");
                    }
                },
            ],
        ];
    }

    /**
     * رسائل الخطأ المخصصة باللغة العربية
     */
    public function messages(): array
    {
        return [
            'name.required' => 'حقل اسم المستند مطلوب.',
            'name.max' => 'اسم المستند طويل جداً.',

            'file.required' => 'يجب اختيار ملف للرفع.',
            'file.mimes' => 'نوع الملف غير مدعوم. الامتدادات المسموحة فقط: PDF, JPG, PNG, MP4, MOV.',
            'file.max' => 'حجم الملف كبير جداً (أكبر من 50 ميغا). يرجى تقليل الحجم والمحاولة مرة أخرى.',

            // هذا الخطأ يظهر عندما يرفض PHP الملف قبل وصوله للارافيل
            'file.uploaded' => 'فشل رفع الملف. غالباً حجم الملف يتجاوز الحد المسموح به في إعدادات السيرفر (php.ini).',

            'target_type.required' => 'نوع الجهة (شركة أو مشروع) مطلوب.',
            'target_type.in' => 'قيمة نوع الجهة غير صحيحة.',
            'target_id.required' => 'معرف الجهة مطلوب.',
        ];
    }
}
