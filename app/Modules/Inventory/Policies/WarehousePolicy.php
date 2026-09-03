<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Policies;

use App\Models\User;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Auth\Access\HandlesAuthorization;

class WarehousePolicy
{
    use HandlesAuthorization;

    /**
     * تحديد إمكانية عرض قائمة المستودعات
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('inventory.warehouses.view', 'api');
    }

    /**
     * تحديد إمكانية عرض تفاصيل مستودع معين
     */
    public function view(User $user, Warehouse $warehouse): bool
    {
        return $user->hasPermissionTo('inventory.warehouses.view', 'api');
    }

    /**
     * تحديد إمكانية إنشاء مستودع جديد
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('inventory.warehouses.create', 'api');
    }

    /**
     * تحديد إمكانية تعديل بيانات مستودع
     */
    public function update(User $user, Warehouse $warehouse): bool
    {
        return $user->hasPermissionTo('inventory.warehouses.update', 'api');
    }

    /**
     * تحديد إمكانية حذف مستودع
     */
    public function delete(User $user, Warehouse $warehouse): bool
    {
        return $user->hasPermissionTo('inventory.warehouses.delete', 'api');
    }

    /**
     * تحديد إمكانية استعادة مستودع محذوف مؤقتاً
     */
    public function restore(User $user, Warehouse $warehouse): bool
    {
        return $user->hasPermissionTo('inventory.warehouses.delete', 'api');
    }

    /**
     * تحديد إمكانية الحذف النهائي للمستودع
     */
    public function forceDelete(User $user, Warehouse $warehouse): bool
    {
        return $user->hasPermissionTo('inventory.warehouses.delete', 'api');
    }
}
