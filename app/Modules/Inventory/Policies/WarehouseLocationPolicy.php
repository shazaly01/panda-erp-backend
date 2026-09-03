<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Policies;

use App\Models\User;
use App\Modules\Inventory\Models\WarehouseLocation;
use Illuminate\Auth\Access\HandlesAuthorization;

class WarehouseLocationPolicy
{
    use HandlesAuthorization;

    /**
     * تحديد إمكانية عرض قائمة مواقع المستودعات
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('inventory.warehouses.view', 'api');
    }

    /**
     * تحديد إمكانية عرض تفاصيل موقع معين
     */
    public function view(User $user, WarehouseLocation $location): bool
    {
        return $user->hasPermissionTo('inventory.warehouses.view', 'api');
    }

    /**
     * تحديد إمكانية إنشاء موقع مستودع جديد
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('inventory.warehouses.create', 'api');
    }

    /**
     * تحديد إمكانية تعديل بيانات موقع مستودع
     */
    public function update(User $user, WarehouseLocation $location): bool
    {
        return $user->hasPermissionTo('inventory.warehouses.update', 'api');
    }

    /**
     * تحديد إمكانية حذف موقع مستودع
     */
    public function delete(User $user, WarehouseLocation $location): bool
    {
        return $user->hasPermissionTo('inventory.warehouses.delete', 'api');
    }

    /**
     * تحديد إمكانية استعادة موقع مستودع محذوف
     */
    public function restore(User $user, WarehouseLocation $location): bool
    {
        return $user->hasPermissionTo('inventory.warehouses.delete', 'api');
    }

    /**
     * تحديد إمكانية الحذف النهائي للموقع
     */
    public function forceDelete(User $user, WarehouseLocation $location): bool
    {
        return $user->hasPermissionTo('inventory.warehouses.delete', 'api');
    }
}