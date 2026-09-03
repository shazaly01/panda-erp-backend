<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Policies;

use App\Models\User;
use App\Modules\Purchasing\Enums\ReceiptStatus;
use App\Modules\Purchasing\Models\PurchaseReceipt;
use Illuminate\Auth\Access\HandlesAuthorization;

class PurchaseReceiptPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('purchasing.receipts.view');
    }

    public function view(User $user, PurchaseReceipt $receipt): bool
    {
        return $user->hasPermissionTo('purchasing.receipts.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('purchasing.receipts.create');
    }

    public function update(User $user, PurchaseReceipt $receipt): bool
    {
        if (! $user->hasPermissionTo('purchasing.receipts.update')) {
            return false;
        }

        // التعديل مسموح فقط إذا كان سند الاستلام مسودة
        return $receipt->status === ReceiptStatus::DRAFT;
    }

    public function delete(User $user, PurchaseReceipt $receipt): bool
    {
        if (! $user->hasPermissionTo('purchasing.receipts.delete')) {
            return false;
        }

        // الحذف مقتصر على المسودات فقط
        return $receipt->status === ReceiptStatus::DRAFT;
    }

    public function receive(User $user, PurchaseReceipt $receipt): bool
    {
        if (! $user->hasPermissionTo('purchasing.receipts.receive')) {
            return false;
        }

        // تأكيد الاستلام وترحيل المخزون يتم للمسودات فقط
        return $receipt->status === ReceiptStatus::DRAFT;
    }

    public function cancel(User $user, PurchaseReceipt $receipt): bool
    {
        if (! $user->hasPermissionTo('purchasing.receipts.cancel')) {
            return false;
        }

        // لا يمكن إلغاء سند ملغي مسبقاً
        return $receipt->status !== ReceiptStatus::CANCELLED;
    }
}