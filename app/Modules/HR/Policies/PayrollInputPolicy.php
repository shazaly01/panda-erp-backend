<?php

declare(strict_types=1);

namespace App\Modules\HR\Policies;

use App\Models\User;
use App\Modules\HR\Models\PayrollInput;
use Illuminate\Auth\Access\HandlesAuthorization;

class PayrollInputPolicy
{
    use HandlesAuthorization;

    public function before(User $user, $ability)
    {
        if ($user->hasRole('HR Manager')) {
            return true;
        }
    }

    /**
     * عرض قائمة المدخلات (الحوافز والخصومات)
     */
    public function viewAny(User $user): bool
    {
        // يمكن لموظف الـ HR عرضها، أو للموظف العادي عرض حوافزه وخصوماته فقط
        return $user->hasPermissionTo('hr.payroll_inputs.view') || !is_null($user->employee_id);
    }

    /**
     * عرض تفاصيل مدخل مالي محدد
     */
    public function view(User $user, PayrollInput $payrollInput): bool
    {
        return $user->hasPermissionTo('hr.payroll_inputs.view') || $user->employee_id === $payrollInput->employee_id;
    }

    /**
     * إضافة حافز أو خصم لموظف
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('hr.payroll_inputs.manage');
    }

    /**
     * تعديل حافز/خصم (مسموح فقط إذا لم يتم معالجته في مسير الرواتب)
     */
    public function update(User $user, PayrollInput $payrollInput): bool
    {
        if (!$user->hasPermissionTo('hr.payroll_inputs.manage')) {
            return false;
        }

        // حماية محاسبية: لا يمكن تعديل حركة مالية دخلت في مسير رواتب وتم ترحيلها
        return $payrollInput->is_processed === false;
    }

    /**
     * إلغاء حافز/خصم
     */
    public function delete(User $user, PayrollInput $payrollInput): bool
    {
        if (!$user->hasPermissionTo('hr.payroll_inputs.manage')) {
            return false;
        }

        // نفس الحماية: لا يحذف إذا تمت معالجته
        return $payrollInput->is_processed === false;
    }
}
