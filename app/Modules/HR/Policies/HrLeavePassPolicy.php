<?php

declare(strict_types=1);

namespace App\Modules\HR\Policies;

use App\Models\User;
use App\Modules\HR\Models\HrLeavePass;
use Illuminate\Auth\Access\HandlesAuthorization;

class HrLeavePassPolicy
{
    use HandlesAuthorization;

    /**
     * التحقق من صلاحية عرض قائمة الأذونات أو شاشة الإخلاء الميداني
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('hr_leave_passes.view', 'api');
    }

    /**
     * التحقق من صلاحية عرض تفاصيل إذن معين
     */
    public function view(User $user, HrLeavePass $leavePass): bool
    {
        // يسمح للموظف برؤية إذنه الخاص، أو لمن يملك صلاحية العرض الإدارية العامة
        return $user->id === $leavePass->employee->user_id || $user->hasPermissionTo('hr_leave_passes.view', 'api');
    }

    /**
     * التحقق من صلاحية إنشاء طلب إذن جديد
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('hr_leave_passes.create', 'api');
    }

    /**
     * التحقق من صلاحية تعديل الإذن (متاح فقط طالما الطلب قيد الانتظار أو معتمد إدارياً ولم يستغل حركياً)
     */
    public function update(User $user, HrLeavePass $leavePass): bool
    {
        $isValidStatus = in_array($leavePass->status, ['pending', 'approved'], true);

        return $isValidStatus && (
            $user->id === $leavePass->employee->user_id ||
            $user->hasPermissionTo('hr_leave_passes.approve', 'api')
        );
    }

    /**
     * التحقق من صلاحية حذف الطلب مرناً
     */
    public function delete(User $user, HrLeavePass $leavePass): bool
    {
        return $leavePass->status === 'pending' && $user->hasPermissionTo('hr_leave_passes.delete', 'api');
    }

    /**
     * صلاحية خاصة للمشرفين لاعتماد أو رفض طلب الإذن
     */
    public function approve(User $user): bool
    {
        return $user->hasPermissionTo('hr_leave_passes.approve', 'api');
    }

    /**
     * صلاحية خاصة لأفراد حراسة البوابات الخارجية لإثبات الحركات اللحظية يدوياً أو عبر الباركود
     */
    public function gateCheck(User $user, ?HrLeavePass $leavePass = null): bool
    {
        return $user->hasPermissionTo('hr_leave_passes.gate_check', 'api');
    }
}
