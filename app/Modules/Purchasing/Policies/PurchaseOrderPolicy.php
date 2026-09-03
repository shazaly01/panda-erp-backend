<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Policies;

use App\Models\User;
use App\Modules\Purchasing\Enums\PurchaseOrderStatus;
use App\Modules\Purchasing\Models\PurchaseOrder;
use Illuminate\Auth\Access\HandlesAuthorization;

class PurchaseOrderPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('purchasing.orders.view');
    }

    public function view(User $user, PurchaseOrder $order): bool
    {
        return $user->hasPermissionTo('purchasing.orders.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('purchasing.orders.create');
    }

    public function update(User $user, PurchaseOrder $order): bool
    {
        if (! $user->hasPermissionTo('purchasing.orders.update')) {
            return false;
        }

        // التعديل مسموح فقط إذا كان أمر الشراء مسودة
        return $order->status === PurchaseOrderStatus::DRAFT;
    }

    public function delete(User $user, PurchaseOrder $order): bool
    {
        if (! $user->hasPermissionTo('purchasing.orders.delete')) {
            return false;
        }

        // الحذف مقتصر على أوامر الشراء المسودة
        return $order->status === PurchaseOrderStatus::DRAFT;
    }

    public function confirm(User $user, PurchaseOrder $order): bool
    {
        if (! $user->hasPermissionTo('purchasing.orders.confirm')) {
            return false;
        }

        return $order->status === PurchaseOrderStatus::DRAFT;
    }

    public function cancel(User $user, PurchaseOrder $order): bool
    {
        if (! $user->hasPermissionTo('purchasing.orders.cancel')) {
            return false;
        }

        // لا يمكن إلغاء أمر شراء استلمت منه بضائع أو فواتير
        return in_array($order->status, [
            PurchaseOrderStatus::DRAFT,
            PurchaseOrderStatus::CONFIRMED,
        ], true);
    }
}