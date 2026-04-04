<?php

namespace App\Modules\HR\Policies;

use App\Models\User;
use App\Modules\HR\Models\SalaryRule;

class SalaryRulePolicy
{
    /**
     * هل يملك صلاحية عرض القواعد؟
     */
    public function viewAny(User $user): bool
    {
        // استخدام الصلاحية العامة لإعدادات الرواتب
        return $user->hasPermissionTo('hr.settings.manage');
    }

    public function view(User $user, SalaryRule $salaryRule): bool
    {
        return $user->hasPermissionTo('hr.settings.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('hr.settings.manage');
    }

    public function update(User $user, SalaryRule $salaryRule): bool
    {
        return $user->hasPermissionTo('hr.settings.manage');
    }

    public function delete(User $user, SalaryRule $salaryRule): bool
    {
        return $user->hasPermissionTo('hr.settings.manage');
    }
}
