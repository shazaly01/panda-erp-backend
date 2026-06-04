<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\DB;

class ResetPasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * تجهيز البيانات وتنظيفها قبل البدء في عملية التحقق
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            $this->merge([
                'phone' => str_replace(' ', '', $this->phone),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'phone' => [
                'required',
                'string',
                'regex:/^[0-9]{10}$/',
                function ($attribute, $value, $fail) {
                    $fullPhone = config('app.country_code') . $value;
                    if (!DB::table('users')->where('phone', $fullPhone)->exists()) {
                        $fail('رقم الهاتف هذا غير مسجل لدينا.');
                    }
                },
            ],
            'code'     => 'required|string|min:6|max:6',
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * تعديل البيانات بعد النجاح في التحقق لتمريرها مدمجة بالرمز إلى السيرفيس
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated();

        if (isset($validated['phone'])) {
            $validated['phone'] = config('app.country_code') . $validated['phone'];
        }

        return $validated;
    }

    /**
     * Custom error messages
     */
    public function messages(): array
    {
        return [
            'phone.required'     => 'حقل رقم الهاتف مطلوب.',
            'phone.regex'        => 'يجب أن يتكون رقم الهاتف من 10 أرقام فقط.',
            'code.required'      => 'كود التحقق مطلوب.',
            'code.min'           => 'يجب أن يتكون كود التحقق من 6 أرقام.',
            'code.max'           => 'يجب أن يتكون كود التحقق من 6 أرقام.',
            'password.required'  => 'حقل كلمة المرور مطلوب.',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق.',
        ];
    }
}
