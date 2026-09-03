<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Policies;

use App\Models\User;
use App\Modules\Inventory\Models\Transfer;
use Illuminate\Auth\Access\HandlesAuthorization;

class TransferPolicy
{
    use HandlesAuthorization;

    /**
     * تحديد إمكانية عرض قائمة التحويلات
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('inventory.transfers.view', 'api');
    }

    /**
     * تحديد إمكانية عرض تفاصيل امر تحويل معين
     */
    public function view(User $user, Transfer $transfer): bool
    {
        return $user->hasPermissionTo('inventory.transfers.view', 'api');
    }

    /**
     * تحديد إمكانية إنشاء طلب تحويل جديد
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('inventory.transfers.create', 'api');
    }

    /**
     * تحديد إمكانية تعديل أمر تحويل
     */
    public function update(User $user, Transfer $transfer): bool
    {
        return $user->hasPermissionTo('inventory.transfers.update', 'api');
    }

    /**
     * تحديد إمكانية حذف أمر تحويل
     */
    public function delete(User $user, Transfer $transfer): bool
    {
        return $user->hasPermissionTo('inventory.transfers.delete', 'api');
    }

    /**
     * تحديد إمكانية اعتماد وتأكيد أمر التحويل المخزني
     */
    public function approve(User $user, Transfer $transfer): bool
    {
        return $user->hasPermissionTo('inventory.transfers.approve', 'api');
    }

    /**
     * تحديد إمكانية استعادة امر تحويل محذوف
     */
    public function restore(User $user, Transfer $transfer): bool
    {
        return $user->hasPermissionTo('inventory.transfers.delete', 'api');
    }

    /**
     * تحديد إمكانية الحذف النهائي لأمر التحويل
     */
    public function forceDelete(User $user, Transfer $transfer): bool
    {
        return $user->hasPermissionTo('inventory.transfers.delete', 'api');
    }
}
