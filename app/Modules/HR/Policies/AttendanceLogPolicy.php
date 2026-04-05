<?php

declare(strict_types=1);

namespace App\Modules\HR\Policies;

use App\Models\User;
use App\Modules\HR\Models\AttendanceLog;
use Illuminate\Auth\Access\HandlesAuthorization;

class AttendanceLogPolicy
{
    use HandlesAuthorization;

    public function before(User $user, $ability)
    {
        if ($user->hasRole('HR Manager')) {
            return true;
        }
    }

    /**
     * عرض قائمة سجلات الحضور
     */
    public function viewAny(User $user): bool
    {
        // إما موظف HR لمشاهدة حضور الكل، أو موظف عادي لمشاهدة حضوره فقط
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
     * إنشاء سجل حضور يدوي (ممنوع على الموظف العادي - مسموح للـ HR فقط في حالات نسيان البصمة)
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
}
