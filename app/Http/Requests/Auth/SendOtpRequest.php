<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SendOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone'      => 'required|string|min:9|max:15',
            'build_mode' => 'sometimes|string|in:debug,release',
        ];
    }
}
