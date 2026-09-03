<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Policies;

use App\Models\User;
use App\Modules\Inventory\Models\ProductionOrder;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProductionOrderPolicy
{
    use HandlesAuthorization;

    /**
     * تحديد إمكانية عرض قائمة أوامر الإنتاج
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('inventory.production_orders.view', 'api');
    }

    /**
     * تحديد إمكانية عرض تفاصيل أمر إنتاج معين
     */
    public function view(User $user, ProductionOrder $productionOrder): bool
    {
        return $user->hasPermissionTo('inventory.production_orders.view', 'api');
    }

    /**
     * تحديد إمكانية إنشاء أمر إنتاج جديد
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('inventory.production_orders.create', 'api');
    }

    /**
     * تحديد إمكانية تعديل أمر إنتاج
     */
    public function update(User $user, ProductionOrder $productionOrder): bool
    {
        return $user->hasPermissionTo('inventory.production_orders.update', 'api');
    }

    /**
     * تحديد إمكانية حذف أمر إنتاج
     */
    public function delete(User $user, ProductionOrder $productionOrder): bool
    {
        return $user->hasPermissionTo('inventory.production_orders.delete', 'api');
    }

    /**
     * تحديد إمكانية اعتماد وتنفيذ أمر الإنتاج
     */
    public function approve(User $user, ProductionOrder $productionOrder): bool
    {
        return $user->hasPermissionTo('inventory.production_orders.approve', 'api');
    }

    /**
     * تحديد إمكانية استعادة أمر إنتاج محذوف
     */
    public function restore(User $user, ProductionOrder $productionOrder): bool
    {
        return $user->hasPermissionTo('inventory.production_orders.delete', 'api');
    }

    /**
     * تحديد إمكانية الحذف النهائي لأمر الإنتاج
     */
    public function forceDelete(User $user, ProductionOrder $productionOrder): bool
    {
        return $user->hasPermissionTo('inventory.production_orders.delete', 'api');
    }
}
