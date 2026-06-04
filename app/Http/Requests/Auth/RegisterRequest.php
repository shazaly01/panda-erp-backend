<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;
class RegisterRequest extends FormRequest
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
            $cleanedPhone = str_replace(' ', '', $this->phone);

            $this->merge([
                'phone' => $cleanedPhone,
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
        $fullPhone = config('app.country_code') . $this->phone;

        return [
            'full_name' => 'required|string|max:255',
            'username'  => 'nullable|string|max:255|unique:users,username',
            'phone'     => [
                'required',
                'string',
                'regex:/^[0-9]{10}$/',
                // نتحقق من عدم تكرار الرقم الكامل (المفتاح + الرقم) عبر تخصيص الفحص
                Rule::unique('users', 'phone')->where(fn ($query) => $query->where('phone', $fullPhone))
            ],
            'password'  => ['required', 'confirmed', Password::defaults()],
            'code'      => 'required|string|min:6|max:6',
        ];
    }

    /**
     * تعديل البيانات بعد النجاح لتُخزن في القاعدة مدمجة بالمفتاح الدولي مباشرة
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
            'full_name.required' => 'حقل الاسم الكامل مطلوب.',
            'phone.required'     => 'حقل رقم الهاتف مطلوب.',
            'phone.regex'        => 'يجب أن يتكون رقم الهاتف من 10 أرقام فقط.',
            'phone.unique'       => 'رقم الهاتف هذا مسجل بالفعل.',
            'password.required'  => 'حقل كلمة المرور مطلوب.',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق.',
            'code.required'      => 'كود التحقق مطلوب.',
        ];
    }
}
