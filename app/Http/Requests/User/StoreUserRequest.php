<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'nullable|string|email|max:255',
            'password' => ['required', 'confirmed', Password::defaults()],
            'roles' => 'required|array',
            'roles.*' => 'string|exists:roles,name,guard_name,api',

            // --- [الافتراضيات الذكية] ---
            'default_cost_center_id'  => 'nullable|integer|exists:cost_centers,id',
            'default_box_id'          => 'nullable|integer|exists:boxes,id',
            'default_bank_account_id' => 'nullable|integer|exists:bank_accounts,id',
        ];
    }
}
