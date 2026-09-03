<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Policies;

use App\Models\User;
use App\Modules\Inventory\Models\Unit;
use Illuminate\Auth\Access\HandlesAuthorization;

class UnitPolicy
{
    use HandlesAuthorization;

    /**
     * تحديد إمكانية عرض قائمة الوحدات
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('inventory.units.view', 'api');
    }

    /**
     * تحديد إمكانية عرض تفاصيل وحدة معينة
     */
    public function view(User $user, Unit $unit): bool
    {
        return $user->hasPermissionTo('inventory.units.view', 'api');
    }

    /**
     * تحديد إمكانية إنشاء وحدة جديدة
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('inventory.units.create', 'api');
    }

    /**
     * تحديد إمكانية تعديل وحدة
     */
    public function update(User $user, Unit $unit): bool
    {
        return $user->hasPermissionTo('inventory.units.update', 'api');
    }

    /**
     * تحديد إمكانية حذف وحدة
     */
    public function delete(User $user, Unit $unit): bool
    {
        return $user->hasPermissionTo('inventory.units.delete', 'api');
    }

    /**
     * تحديد إمكانية استعادة وحدة محذوفة مؤقتاً
     */
    public function restore(User $user, Unit $unit): bool
    {
        return $user->hasPermissionTo('inventory.units.delete', 'api');
    }

    /**
     * تحديد إمكانية الحذف النهائي للوحدة
     */
    public function forceDelete(User $user, Unit $unit): bool
    {
        return $user->hasPermissionTo('inventory.units.delete', 'api');
    }
}
