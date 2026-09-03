<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Policies;

use App\Models\User;
use App\Modules\Purchasing\Enums\PurchaseReturnStatus;
use App\Modules\Purchasing\Models\PurchaseReturn;
use Illuminate\Auth\Access\HandlesAuthorization;

class PurchaseReturnPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('purchasing.returns.view');
    }

    public function view(User $user, PurchaseReturn $return): bool
    {
        return $user->hasPermissionTo('purchasing.returns.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('purchasing.returns.create');
    }

    public function update(User $user, PurchaseReturn $return): bool
    {
        if (! $user->hasPermissionTo('purchasing.returns.update')) {
            return false;
        }

        // التعديل مسموح فقط إذا كان المرتجع مسودة
        return $return->status === PurchaseReturnStatus::DRAFT;
    }

    public function delete(User $user, PurchaseReturn $return): bool
    {
        if (! $user->hasPermissionTo('purchasing.returns.delete')) {
            return false;
        }

        // الحذف مقتصر على المرتجعات المسودة فقط
        return $return->status === PurchaseReturnStatus::DRAFT;
    }

    public function post(User $user, PurchaseReturn $return): bool
    {
        if (! $user->hasPermissionTo('purchasing.returns.post')) {
            return false;
        }

        // الترحيل وعكس الأثر المخزني والمالي يتم فقط للمسودة
        return $return->status === PurchaseReturnStatus::DRAFT;
    }

    public function cancel(User $user, PurchaseReturn $return): bool
    {
        if (! $user->hasPermissionTo('purchasing.returns.cancel')) {
            return false;
        }

        // لا يمكن إلغاء مرتجع ملغي مسبقاً
        return $return->status !== PurchaseReturnStatus::CANCELLED;
    }
}