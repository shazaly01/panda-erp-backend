<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Policies;

use App\Models\User;
use App\Modules\Inventory\Models\StockBatch;
use Illuminate\Auth\Access\HandlesAuthorization;

class StockBatchPolicy
{
    use HandlesAuthorization;

    /**
     * تحديد إمكانية عرض قائمة الباتشات/التشغيلات
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('inventory.batches.view', 'api');
    }

    /**
     * تحديد إمكانية عرض تفاصيل تشغيلة معينة
     */
    public function view(User $user, StockBatch $stockBatch): bool
    {
        return $user->hasPermissionTo('inventory.batches.view', 'api');
    }

    /**
     * تحديد إمكانية إضافة تشغيلة/باتش جديد
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('inventory.batches.create', 'api');
    }

    /**
     * تحديد إمكانية تعديل بيانات التشغيلة
     */
    public function update(User $user, StockBatch $stockBatch): bool
    {
        return $user->hasPermissionTo('inventory.batches.update', 'api');
    }

    /**
     * تحديد إمكانية حذف تشغيلة
     */
    public function delete(User $user, StockBatch $stockBatch): bool
    {
        return $user->hasPermissionTo('inventory.batches.delete', 'api');
    }

    /**
     * تحديد إمكانية استعادة تشغيلة محذوفة
     */
    public function restore(User $user, StockBatch $stockBatch): bool
    {
        return $user->hasPermissionTo('inventory.batches.delete', 'api');
    }

    /**
     * تحديد إمكانية الحذف النهائي للتشغيلة
     */
    public function forceDelete(User $user, StockBatch $stockBatch): bool
    {
        return $user->hasPermissionTo('inventory.batches.delete', 'api');
    }
}
