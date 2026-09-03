<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Policies;

use App\Models\User;
use App\Modules\Inventory\Models\Bom;
use Illuminate\Auth\Access\HandlesAuthorization;

class BomPolicy
{
    use HandlesAuthorization;

    /**
     * تحديد إمكانية عرض قائمة قوائم المكونات
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('inventory.boms.view', 'api');
    }

    /**
     * تحديد إمكانية عرض تفاصيل قائمة مكونات معينة
     */
    public function view(User $user, Bom $bom): bool
    {
        return $user->hasPermissionTo('inventory.boms.view', 'api');
    }

    /**
     * تحديد إمكانية إنشاء قائمة مكونات جديدة
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('inventory.boms.create', 'api');
    }

    /**
     * تحديد إمكانية تعديل قائمة مكونات
     */
    public function update(User $user, Bom $bom): bool
    {
        return $user->hasPermissionTo('inventory.boms.update', 'api');
    }

    /**
     * تحديد إمكانية حذف قائمة مكونات
     */
    public function delete(User $user, Bom $bom): bool
    {
        return $user->hasPermissionTo('inventory.boms.delete', 'api');
    }

    /**
     * تحديد إمكانية استعادة قائمة مكونات محذوفة
     */
    public function restore(User $user, Bom $bom): bool
    {
        return $user->hasPermissionTo('inventory.boms.delete', 'api');
    }

    /**
     * تحديد إمكانية الحذف النهائي لقائمة المكونات
     */
    public function forceDelete(User $user, Bom $bom): bool
    {
        return $user->hasPermissionTo('inventory.boms.delete', 'api');
    }
}
