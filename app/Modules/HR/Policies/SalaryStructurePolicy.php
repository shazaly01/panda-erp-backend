<?php

namespace App\Modules\HR\Policies;

use App\Models\User;
use App\Modules\HR\Models\SalaryStructure;

class SalaryStructurePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.settings.manage');
    }

    public function view(User $user, SalaryStructure $salaryStructure): bool
    {
        return $user->can('hr.settings.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('hr.settings.manage');
    }

    public function update(User $user, SalaryStructure $salaryStructure): bool
    {
        return $user->can('hr.settings.manage');
    }

    public function delete(User $user, SalaryStructure $salaryStructure): bool
    {
        return $user->can('hr.settings.manage');
    }
}
