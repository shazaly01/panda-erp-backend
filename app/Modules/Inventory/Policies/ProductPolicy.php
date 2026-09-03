<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Policies;

use App\Models\User;
use App\Modules\Inventory\Models\Product;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProductPolicy
{
    use HandlesAuthorization;

    /**
     * تحديد إمكانية عرض قائمة المنتجات
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('inventory.products.view', 'api');
    }

    /**
     * تحديد إمكانية عرض تفاصيل منتج معين
     */
    public function view(User $user, Product $product): bool
    {
        return $user->company_id === $product->company_id
            && $user->hasPermissionTo('inventory.products.view', 'api');
    }

    /**
     * تحديد إمكانية إضافة منتج جديد
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('inventory.products.create', 'api');
    }

    /**
     * تحديد إمكانية تعديل بيانات منتج
     */
    public function update(User $user, Product $product): bool
    {
        return $user->company_id === $product->company_id
            && $user->hasPermissionTo('inventory.products.update', 'api');
    }

    /**
     * تحديد إمكانية حذف منتج
     */
    public function delete(User $user, Product $product): bool
    {
        return $user->company_id === $product->company_id
            && $user->hasPermissionTo('inventory.products.delete', 'api');
    }

    /**
     * تحديد إمكانية استعادة منتج محذوف
     */
    public function restore(User $user, Product $product): bool
    {
        return $user->company_id === $product->company_id
            && $user->hasPermissionTo('inventory.products.delete', 'api');
    }

    /**
     * تحديد إمكانية الحذف النهائي للمنتج
     */
    public function forceDelete(User $user, Product $product): bool
    {
        return $user->company_id === $product->company_id
            && $user->hasPermissionTo('inventory.products.delete', 'api');
    }
}