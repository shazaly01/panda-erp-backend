<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Policies;

use App\Models\User;
use App\Modules\Purchasing\Enums\BillStatus;
use App\Modules\Purchasing\Models\PurchaseBill;
use Illuminate\Auth\Access\HandlesAuthorization;

class PurchaseBillPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('purchasing.bills.view');
    }

    public function view(User $user, PurchaseBill $bill): bool
    {
        return $user->hasPermissionTo('purchasing.bills.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('purchasing.bills.create');
    }

    public function update(User $user, PurchaseBill $bill): bool
    {
        if (! $user->hasPermissionTo('purchasing.bills.update')) {
            return false;
        }

        // التعديل مسموح فقط إذا كانت الفاتورة مسودة
        return $bill->status === BillStatus::DRAFT;
    }

    public function delete(User $user, PurchaseBill $bill): bool
    {
        if (! $user->hasPermissionTo('purchasing.bills.delete')) {
            return false;
        }

        // الحذف مقتصر على فواتير المسودة فقط
        return $bill->status === BillStatus::DRAFT;
    }

    public function post(User $user, PurchaseBill $bill): bool
    {
        if (! $user->hasPermissionTo('purchasing.bills.post')) {
            return false;
        }

        // الترحيل المالي وتوليد القيد المحاسبي يتم فقط للمسودات
        return $bill->status === BillStatus::DRAFT;
    }

    public function cancel(User $user, PurchaseBill $bill): bool
    {
        if (! $user->hasPermissionTo('purchasing.bills.cancel')) {
            return false;
        }

        // لا يمكن إلغاء فاتورة تم سداد جزء منها أو ملغية مسبقاً
        return in_array($bill->status, [
            BillStatus::DRAFT,
            BillStatus::POSTED,
        ], true) && (float) $bill->paid_amount <= 0.0001;
    }
}