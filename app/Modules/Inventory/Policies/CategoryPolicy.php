<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Policies;

use App\Models\User;
use App\Modules\Inventory\Models\Category;
use Illuminate\Auth\Access\HandlesAuthorization;

class CategoryPolicy
{
    use HandlesAuthorization;

    /**
     * تحديد إمكانية عرض قائمة التصنيفات
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('inventory.categories.view', 'api');
    }

    /**
     * تحديد إمكانية عرض تصنيف معين
     */
    public function view(User $user, Category $category): bool
    {
        return $user->hasPermissionTo('inventory.categories.view', 'api');
    }

    /**
     * تحديد إمكانية إنشاء تصنيف جديد
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('inventory.categories.create', 'api');
    }

    /**
     * تحديد إمكانية تعديل تصنيف
     */
    public function update(User $user, Category $category): bool
    {
        return $user->hasPermissionTo('inventory.categories.update', 'api');
    }

    /**
     * تحديد إمكانية حذف تصنيف
     */
    public function delete(User $user, Category $category): bool
    {
        return $user->hasPermissionTo('inventory.categories.delete', 'api');
    }

    /**
     * تحديد إمكانية استعادة تصنيف محذوف
     */
    public function restore(User $user, Category $category): bool
    {
        return $user->hasPermissionTo('inventory.categories.delete', 'api');
    }

    /**
     * تحديد إمكانية الحذف النهائي لتصنيف
     */
    public function forceDelete(User $user, Category $category): bool
    {
        return $user->hasPermissionTo('inventory.categories.delete', 'api');
    }
}
