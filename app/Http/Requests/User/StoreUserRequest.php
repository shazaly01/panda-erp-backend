<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    /**
     * تحديد ما إذا كان المستخدم مصرحاً له بإجراء هذا الطلب
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * قواعد التحقق المطبقة على الطلب
     */
    public function rules(): array
    {
        return [
            'full_name' => 'required|string|max:255',

            // إضافة حقل الهاتف ليكون إجبارياً وفريداً في جدول المستخدمين
            'phone'     => 'required|string|max:20|unique:users,phone',

            // تعديل اسم المستخدم ليكون اختيارياً (nullable) ولكنه يظل فريداً إذا كُتب
            'username'  => 'nullable|string|max:255|unique:users,username',

            'email'     => 'nullable|string|email|max:255',

            // إزالة قواعد كلمة المرور بالكامل لأن الحساب ينشأ بدونها من طرف المشرف
            'roles'     => 'required|array',
            'roles.*'   => 'string|exists:roles,name,guard_name,api',

            // --- [الافتراضيات الذكية للربط المحاسبي] ---
            'default_cost_center_id'  => 'nullable|integer|exists:cost_centers,id',
            'default_box_id'          => 'nullable|integer|exists:boxes,id',
            'default_bank_account_id' => 'nullable|integer|exists:bank_accounts,id',
        ];
    }
}
