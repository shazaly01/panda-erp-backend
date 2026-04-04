<?php

namespace App\Modules\HR\Policies;

use App\Models\User;
use App\Modules\HR\Models\Department;

class DepartmentPolicy
{
    /**
     * هل يملك صلاحية عرض قائمة الإدارات؟
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('hr.departments.view');
    }

    /**
     * هل يملك صلاحية عرض تفاصيل إدارة محددة؟
     */
    public function view(User $user, Department $department): bool
    {
        return $user->hasPermissionTo('hr.departments.view');
    }

    /**
     * هل يملك صلاحية إنشاء إدارة جديدة؟
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('hr.departments.create');
    }

    /**
     * هل يملك صلاحية تعديل إدارة؟
     */
    public function update(User $user, Department $department): bool
    {
        return $user->hasPermissionTo('hr.departments.update');
    }

    /**
     * هل يملك صلاحية حذف إدارة؟
     */
    public function delete(User $user, Department $department): bool
    {
        return $user->hasPermissionTo('hr.departments.delete');
    }
}
