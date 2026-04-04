<?php

namespace App\Modules\HR\Policies;

use App\Models\User;
use App\Modules\HR\Models\Employee;

class EmployeePolicy
{
    /**
     * عرض القائمة (للمدراء فقط)
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('hr.employees.view');
    }

    /**
     * عرض ملف موظف محدد
     */
    public function view(User $user, Employee $employee): bool
    {
        // 1. المستخدم هو نفس الموظف (صلاحية ذاتية)
        if ($user->id === $employee->user_id) {
            return true;
        }

        // 2. أو لديه صلاحية مدير
        return $user->hasPermissionTo('hr.employees.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('hr.employees.create');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->hasPermissionTo('hr.employees.update');
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->hasPermissionTo('hr.employees.delete');
    }
}

