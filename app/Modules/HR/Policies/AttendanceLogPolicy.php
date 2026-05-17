<?php

declare(strict_types=1);

namespace App\Modules\HR\Policies;

use App\Models\User;
use App\Modules\HR\Models\AttendanceLog;
use Illuminate\Auth\Access\HandlesAuthorization;

class AttendanceLogPolicy
{
    use HandlesAuthorization;

    /**
     * تجاوز صلاحيات مدير الموارد البشرية على مستوى الموديول
     * ملاحظة: تجاوز الـ Super Admin العام يجب أن يبقى في AuthServiceProvider (Gate::before)
     */
    public function before(User $user, $ability)
    {
        if ($user->hasRole('HR Manager')) {
            return true;
        }
    }

    /**
     * عرض قائمة سجلات الحضور (للـ HR أو للموظف نفسه)
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('hr.attendance.view') || !is_null($user->employee_id);
    }

    /**
     * عرض سجل حضور ليوم محدد
     */
    public function view(User $user, AttendanceLog $attendanceLog): bool
    {
        return $user->hasPermissionTo('hr.attendance.view') || $user->employee_id === $attendanceLog->employee_id;
    }

    /**
     * إنشاء سجل حضور يدوي (مسموح للـ HR)
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('hr.attendance.manage');
    }

    /**
     * تعديل السجل (لتبرير غياب أو مسح تأخير)
     */
    public function update(User $user, AttendanceLog $attendanceLog): bool
    {
        return $user->hasPermissionTo('hr.attendance.manage');
    }

    /**
     * حذف السجل (عملية حساسة جداً)
     */
    public function delete(User $user, AttendanceLog $attendanceLog): bool
    {
        return $user->hasPermissionTo('hr.attendance.manage');
    }

    // =================================================================
    // 🌟 صلاحيات الخدمة الذاتية للمدير (Manager Self-Service) 🌟
    // =================================================================

    /**
     * السماح للمشرف بإدارة وعرض مصفوفة حضور فريقه
     */
    public function manageTeam(User $user): bool
    {
        return $user->hasPermissionTo('hr.team_attendance.manage');
    }
}
