<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    /**
     * تحديد ما إذا كان المستخدم مصرحاً له بإجراء هذا الطلب
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * قواعد التحقق المطبقة على طلب التحديث
     */
    public function rules(): array
    {
        // جلب معرف المستخدم الجاري تعديله بأمان من متغيرات المسار (Route Parameter)
        $userId = $this->route('user')?->id ?? $this->route('user');

        return [
            'full_name' => 'required|string|max:255',

            // الهاتف إجباري ويجب أن يكون فريداً مع استثناء الحساب الحالي لتفادي خطأ التكرار أثناء الحفظ
            'phone'     => 'required|string|max:20|unique:users,phone,' . $userId,

            // اسم المستخدم اختياري وفريد مع استثناء الحساب الحالي كذلك
            'username'  => 'nullable|string|max:255|unique:users,username,' . $userId,

            'email'     => 'nullable|string|email|max:255',

            'roles'     => 'required|array',
            'roles.*'   => 'string|exists:roles,name,guard_name,api',

            // --- [الافتراضيات الذكية للربط المحاسبي] ---
            'default_cost_center_id'  => 'nullable|integer|exists:cost_centers,id',
            'default_box_id'          => 'nullable|integer|exists:boxes,id',
            'default_bank_account_id' => 'nullable|integer|exists:bank_accounts,id',
        ];
    }
}
