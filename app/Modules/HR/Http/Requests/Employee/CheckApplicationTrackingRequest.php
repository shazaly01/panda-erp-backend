<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class CheckApplicationTrackingRequest extends FormRequest
{
    /**
     * السماح لجميع المتقدمين بالوصول لرابط المتابعة دون تسجيل دخول رسمي
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * قوانين التحقق الصارمة لبيانات الدخول والمتابعة
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'min:10', 'max:50'],
            'tracking_code' => ['required', 'string', 'size:5'],
        ];
    }

    /**
     * رسائل الخطأ المخصصة باللغة العربية
     */
    public function messages(): array
    {
        return [
            'phone.required' => 'رقم الهاتف مطلوب لدخول بوابة المتابعة.',
            'tracking_code.required' => 'كود المتابعة المكون من 5 أرقام مطلوب.',
            'tracking_code.size' => 'يجب أن يتكون كود المتابعة من 5 أرقام تماماً.',
        ];
    }
}
