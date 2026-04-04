<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'full_name' => 'required|string|max:255',
            'username' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'email' => [
                'nullable',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'roles' => 'required|array',
            'roles.*' => 'string|exists:roles,name,guard_name,api',

            // --- [الافتراضيات الذكية] ---
            'default_cost_center_id'  => 'nullable|integer|exists:cost_centers,id',
            'default_box_id'          => 'nullable|integer|exists:boxes,id',
            'default_bank_account_id' => 'nullable|integer|exists:bank_accounts,id',
        ];
    }
}
