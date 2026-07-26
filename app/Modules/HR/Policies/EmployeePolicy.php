<?php

declare(strict_types=1);

namespace App\Modules\HR\Policies;

use App\Models\User;
use App\Modules\HR\Models\Employee;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.employees.view');
    }

    public function view(User $user, Employee $employee): bool
    {
        if ($user->id === $employee->user_id) {
            return true;
        }

        return $user->can('hr.employees.view');
    }

    public function create(User $user): bool
    {
        return $user->can('hr.employees.create');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->can('hr.employees.update');
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->can('hr.employees.delete');
    }

    /**
     * صلاحية تثبيت المتدرب كموظف دائم
     */
    public function convert(User $user, Employee $employee): bool
    {
        return $user->can('hr.employees.convert');
    }
}
