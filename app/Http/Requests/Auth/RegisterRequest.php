<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'full_name' => 'required|string|max:255',
            'username'  => 'nullable|string|max:255|unique:users,username', // أصبح اختيارياً
            'phone'     => 'required|string|min:9|max:15|unique:users,phone', // معرّف إلزامي وفريد
            'password'  => ['required', 'confirmed', Password::defaults()],
            'code'      => 'required|string|min:6|max:6',
        ];
    }
}
