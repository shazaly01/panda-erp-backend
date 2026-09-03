<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Policies;

use App\Models\User;
use App\Modules\Inventory\Models\Adjustment;
use Illuminate\Auth\Access\HandlesAuthorization;

class AdjustmentPolicy
{
    use HandlesAuthorization;

    /**
     * تحديد إمكانية عرض قائمة التسويات المخزنية
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('inventory.adjustments.view', 'api');
    }

    /**
     * تحديد إمكانية عرض تفاصيل تسوية معينة
     */
    public function view(User $user, Adjustment $adjustment): bool
    {
        return $user->hasPermissionTo('inventory.adjustments.view', 'api');
    }

    /**
     * تحديد إمكانية إنشاء طلب تسوية/جرد جديد
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('inventory.adjustments.create', 'api');
    }

    /**
     * تحديد إمكانية تعديل طلب تسوية
     */
    public function update(User $user, Adjustment $adjustment): bool
    {
        return $user->hasPermissionTo('inventory.adjustments.update', 'api');
    }

    /**
     * تحديد إمكانية حذف طلب تسوية
     */
    public function delete(User $user, Adjustment $adjustment): bool
    {
        return $user->hasPermissionTo('inventory.adjustments.delete', 'api');
    }

    /**
     * تحديد إمكانية اعتماد التسوية وتطبيق أثرها المالي والمخزني
     */
    public function approve(User $user, Adjustment $adjustment): bool
    {
        return $user->hasPermissionTo('inventory.adjustments.approve', 'api');
    }

    /**
     * تحديد إمكانية استعادة طلب تسوية محذوف
     */
    public function restore(User $user, Adjustment $adjustment): bool
    {
        return $user->hasPermissionTo('inventory.adjustments.delete', 'api');
    }

    /**
     * تحديد إمكانية الحذف النهائي لطلب التسوية
     */
    public function forceDelete(User $user, Adjustment $adjustment): bool
    {
        return $user->hasPermissionTo('inventory.adjustments.delete', 'api');
    }
}
