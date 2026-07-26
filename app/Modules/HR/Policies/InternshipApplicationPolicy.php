<?php

declare(strict_types=1);

namespace App\Modules\HR\Policies;

use App\Models\User;
use App\Modules\HR\Models\InternshipApplication;
use Illuminate\Auth\Access\HandlesAuthorization;

class InternshipApplicationPolicy
{
    use HandlesAuthorization;

    /**
     * التحقق الشامل من امتلاك المستخدم لحيّز صلاحيات التدريب
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'hr.internship_applications.view_pending',
            'hr.internship_applications.view_active',
            'hr.internship_applications.view_completed',
            'hr.internship_applications.view_rejected',
        ]);
    }

    /**
     * صلاحية استعراض طلبات التقديم المعلقة
     */
    public function viewPending(User $user): bool
    {
        return $user->hasPermissionTo('hr.internship_applications.view_pending');
    }

    /**
     * صلاحية استعراض قائمة المتدربين النشطين
     */
    public function viewActive(User $user): bool
    {
        return $user->hasPermissionTo('hr.internship_applications.view_active');
    }

    /**
     * صلاحية استعراض قائمة المتدربين المنتهية فترتهم التدريبية
     */
    public function viewCompleted(User $user): bool
    {
        return $user->hasPermissionTo('hr.internship_applications.view_completed');
    }

    /**
     * صلاحية استعراض قائمة الطلبات المرفوضة
     */
    public function viewRejected(User $user): bool
    {
        return $user->hasPermissionTo('hr.internship_applications.view_rejected');
    }

    /**
     * صلاحية عرض تفاصيل طلب تدريب معين
     */
    public function view(User $user, InternshipApplication $application): bool
    {
        return $this->viewAny($user);
    }

    /**
     * صلاحية فتح وقفل استقبال طلبات التدريب
     */
    public function toggleStatus(User $user): bool
    {
        return $user->hasPermissionTo('hr.internship_applications.toggle_status');
    }

    /**
     * صلاحية اعتماد وقبول طلب التدريب
     */
    public function approve(User $user, InternshipApplication $application): bool
    {
        return $user->hasPermissionTo('hr.internship_applications.approve');
    }

    /**
     * صلاحية رفض طلب التدريب
     */
    public function reject(User $user, InternshipApplication $application): bool
    {
        return $user->hasPermissionTo('hr.internship_applications.reject');
    }

    /**
     * صلاحية حذف طلب التدريب
     */
    public function delete(User $user, InternshipApplication $application): bool
    {
        return $user->hasPermissionTo('hr.internship_applications.delete');
    }
}
