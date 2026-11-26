<?php

declare(strict_types=1);

namespace App\Modules\HR\Policies;

use App\Models\User;
use App\Modules\HR\Models\EmployeeDisciplinaryAction;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmployeeDisciplinaryActionPolicy
{
    use HandlesAuthorization;

    /**
     * تجاوز صلاحيات مدير الموارد البشرية على مستوى الموديول
     * ملاحظة: تجاوز الـ Super Admin العام يتم مركزياً عبر Gate::before
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('HR Manager')) {
            return true;
        }

        return null;
    }

    /**
     * عرض سجل المخالفات والجزاءات (للـ HR أو للموظف نفسه)
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('hr.disciplinary_actions.view')
            || !is_null($user->employee_id);
    }

    /**
     * عرض تفاصيل جزاء محدد
     */
    public function view(User $user, EmployeeDisciplinaryAction $action): bool
    {
        return $user->hasPermissionTo('hr.disciplinary_actions.view')
            || $user->employee_id === $action->employee_id;
    }

    /**
     * تسجيل مخالفة جديدة لموظف
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('hr.disciplinary_actions.create');
    }

    /**
     * اعتماد وتنفيذ الجزاء (بما يشمل الخصم المالي أو الإيقاف أو إنهاء الخدمة)
     */
    public function approve(User $user, EmployeeDisciplinaryAction $action): bool
    {
        return $user->hasPermissionTo('hr.disciplinary_actions.approve');
    }

    /**
     * إلغاء جزاء مسجل أو معتمد والتراجع عن أثره المالي غير المرحّل
     */
    public function cancel(User $user, EmployeeDisciplinaryAction $action): bool
    {
        return $user->hasPermissionTo('hr.disciplinary_actions.cancel');
    }

    /**
     * حذف سجل مخالفة (يُمنع في الخدمة للمخالفات المعتمدة أو المرحلة للرواتب)
     */
    public function delete(User $user, EmployeeDisciplinaryAction $action): bool
    {
        return $user->hasPermissionTo('hr.disciplinary_actions.delete');
    }
}