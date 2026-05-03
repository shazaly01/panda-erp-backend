<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\InternetVoucher;

use Illuminate\Foundation\Http\FormRequest;

class ImportInternetVoucherRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // التحقق من الصلاحية عبر الـ Policy
        return $this->user()->can('import', \App\Modules\HR\Models\InternetVoucher::class);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'], // الحد الأقصى 10 ميجا
        ];
    }

    /**
     * رسائل الخطأ المخصصة
     */
    public function messages(): array
    {
        return [
            'file.required' => 'يرجى إرفاق ملف CSV.',
            'file.mimes'    => 'يجب أن يكون الملف المرفق بصيغة CSV أو TXT.',
            'file.max'      => 'حجم الملف يجب ألا يتجاوز 10 ميجابايت.',
        ];
    }
}
