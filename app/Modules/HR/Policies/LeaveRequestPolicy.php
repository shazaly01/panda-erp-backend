<?php

declare(strict_types=1);

namespace App\Modules\HR\Policies;

use App\Models\User;
use App\Modules\HR\Models\LeaveRequest;
use Illuminate\Auth\Access\HandlesAuthorization;

class LeaveRequestPolicy
{
    use HandlesAuthorization;

    /**
     * استثناء للمدير العام (تخطي جميع الفحوصات إذا كان يملك صلاحية الإدارة المطلقة)
     */
    public function before(User $user, $ability)
    {
        if ($user->hasRole('HR Manager')) {
            return true;
        }
    }

    /**
     * هل يمكن للمستخدم عرض قائمة الإجازات؟
     * نعم، إذا كان موظف HR أو إذا كان يريد عرض قائمة إجازاته الشخصية
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('hr.leaves.view') || $user->hasPermissionTo('hr.leaves.request');
    }

    /**
     * هل يمكن للمستخدم عرض طلب إجازة محدد؟
     */
    public function view(User $user, LeaveRequest $leaveRequest): bool
    {
        // يمكنه المشاهدة إذا كان موظف HR، أو إذا كان هذا الطلب يخصه هو شخصياً
        return $user->hasPermissionTo('hr.leaves.view') || $user->employee_id === $leaveRequest->employee_id;
    }

    /**
     * هل يمكن للمستخدم إنشاء طلب إجازة؟
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('hr.leaves.request');
    }

    /**
     * هل يمكن للمستخدم تعديل طلب إجازة؟
     */
    public function update(User $user, LeaveRequest $leaveRequest): bool
    {
        // 1. موظف الـ HR الذي يملك صلاحية الإدارة يمكنه التعديل
        if ($user->hasPermissionTo('hr.leaves.manage')) {
            return true;
        }

        // 2. الموظف العادي يمكنه التعديل فقط إذا كان الطلب يخصه، وحالته ما زالت "معلق" (Pending)
        return $user->employee_id === $leaveRequest->employee_id && $leaveRequest->status === 'pending';
    }

    /**
     * هل يمكن للمستخدم حذف طلب إجازة؟
     */
    public function delete(User $user, LeaveRequest $leaveRequest): bool
    {
        // الموظف يمكنه الحذف/الإلغاء فقط إذا كان الطلب معلقاً ولم يتم اتخاذ إجراء عليه
        return $user->employee_id === $leaveRequest->employee_id && $leaveRequest->status === 'pending';
    }

    /**
     * هل يمكن للمستخدم اعتماد أو رفض طلب إجازة؟
     */
    public function approve(User $user, LeaveRequest $leaveRequest): bool
    {
        return $user->hasPermissionTo('hr.leaves.approve');
    }
}
