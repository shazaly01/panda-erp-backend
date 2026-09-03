<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Policies;

use App\Models\User;
use App\Modules\Inventory\Models\PriceList;
use Illuminate\Auth\Access\HandlesAuthorization;

class PriceListPolicy
{
    use HandlesAuthorization;

    /**
     * تحديد إمكانية عرض قوائم الأسعار
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('inventory.price_lists.view', 'api');
    }

    /**
     * تحديد إمكانية عرض قائمة أسعار معينة
     */
    public function view(User $user, PriceList $priceList): bool
    {
        return $user->hasPermissionTo('inventory.price_lists.view', 'api');
    }

    /**
     * تحديد إمكانية إنشاء قائمة أسعار جديدة
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('inventory.price_lists.create', 'api');
    }

    /**
     * تحديد إمكانية تعديل قائمة أسعار
     */
    public function update(User $user, PriceList $priceList): bool
    {
        return $user->hasPermissionTo('inventory.price_lists.update', 'api');
    }

    /**
     * تحديد إمكانية حذف قائمة أسعار
     */
    public function delete(User $user, PriceList $priceList): bool
    {
        return $user->hasPermissionTo('inventory.price_lists.delete', 'api');
    }

    /**
     * تحديد إمكانية استعادة قائمة أسعار محذوفة
     */
    public function restore(User $user, PriceList $priceList): bool
    {
        return $user->hasPermissionTo('inventory.price_lists.delete', 'api');
    }

    /**
     * تحديد إمكانية الحذف النهائي لقائمة الأسعار
     */
    public function forceDelete(User $user, PriceList $priceList): bool
    {
        return $user->hasPermissionTo('inventory.price_lists.delete', 'api');
    }
}
