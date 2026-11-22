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
     * التحقق الشامل من امتلاك المستخدم لحيّز صلاحيات التدريب والتوظيف
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
     * صلاحية استعراض طلبات التقديم المعلقة والمعاينات
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
     * صلاحية عرض تفاصيل طلب معين
     */
    public function view(User $user, InternshipApplication $application): bool
    {
        return $this->viewAny($user);
    }

    /**
     * صلاحية إدخال طلب تدريب أو توظيف يدوياً من لوحة الإدارة
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('hr.internship_applications.create');
    }

    /**
     * صلاحية تحديد موعد المعاينة ونقل الطلب لمرحلة المعاينة
     */
    public function scheduleInterview(User $user, InternshipApplication $application): bool
    {
        return $user->hasPermissionTo('hr.internship_applications.interview');
    }

    /**
     * صلاحية فتح وقفل استقبال طلبات التدريب والتوظيف
     */
    public function toggleStatus(User $user): bool
    {
        return $user->hasPermissionTo('hr.internship_applications.toggle_status');
    }

    /**
     * صلاحية اعتماد وقبول الطلب لدخول فترة التدريب
     */
    public function approve(User $user, InternshipApplication $application): bool
    {
        return $user->hasPermissionTo('hr.internship_applications.approve');
    }

    /**
     * صلاحية رفض الطلب
     */
    public function reject(User $user, InternshipApplication $application): bool
    {
        return $user->hasPermissionTo('hr.internship_applications.reject');
    }

    /**
     * صلاحية حذف الطلب
     */
    public function delete(User $user, InternshipApplication $application): bool
    {
        return $user->hasPermissionTo('hr.internship_applications.delete');
    }
}