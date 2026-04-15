<?php

namespace App\Modules\HR\Policies;

use App\Models\User;
use App\Modules\HR\Models\OvertimePolicy;

class OvertimePolicyPolicy
{
    /**
     * عرض قائمة السياسات
     */
    public function viewAny(User $user): bool
    {
        return $user->can('hr.overtime_policies.view');
    }

    /**
     * عرض سياسة معينة
     */
    public function view(User $user, OvertimePolicy $overtimePolicy): bool
    {
        return $user->can('hr.overtime_policies.view');
    }

    /**
     * إنشاء سياسة جديدة
     */
    public function create(User $user): bool
    {
        return $user->can('hr.overtime_policies.manage');
    }

    /**
     * تعديل سياسة
     */
    public function update(User $user, OvertimePolicy $overtimePolicy): bool
    {
        return $user->can('hr.overtime_policies.manage');
    }

    /**
     * حذف سياسة
     */
    public function delete(User $user, OvertimePolicy $overtimePolicy): bool
    {
        return $user->can('hr.overtime_policies.manage');
    }
}
