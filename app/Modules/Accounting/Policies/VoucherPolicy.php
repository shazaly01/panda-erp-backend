<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Policies;

use App\Models\User;
use App\Modules\Accounting\Models\Voucher;
use App\Modules\Accounting\Enums\VoucherType;
use App\Modules\Accounting\Enums\VoucherStatus;
use Illuminate\Auth\Access\HandlesAuthorization;

class VoucherPolicy
{
    use HandlesAuthorization;

    /**
     * عرض القائمة
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('payment.view') || $user->hasPermissionTo('receipt.view');
    }

    /**
     * عرض سند محدد
     */
    public function view(User $user, Voucher $voucher): bool
    {
        return match ($voucher->type) {
            VoucherType::Payment => $user->hasPermissionTo('payment.view'),
            VoucherType::Receipt => $user->hasPermissionTo('receipt.view'),
            default => false,
        };
    }

    /**
     * إنشاء سند جديد
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('payment.create') || $user->hasPermissionTo('receipt.create');
    }

    /**
     * تعديل السند
     * الشرط: (يملك الصلاحية) + (السند في حالة مسودة أو مرفوض)
     */
    public function update(User $user, Voucher $voucher): bool
    {
        if ($voucher->status === VoucherStatus::Posted || $voucher->status === VoucherStatus::Void) {
            return false;
        }

        return match ($voucher->type) {
            VoucherType::Payment => $user->hasPermissionTo('payment.update'),
            VoucherType::Receipt => $user->hasPermissionTo('receipt.update'),
            default => false,
        };
    }

    /**
     * حذف السند
     * الشرط: (يملك الصلاحية) + (السند غير مرحل)
     */
    public function delete(User $user, Voucher $voucher): bool
    {
        if ($voucher->status === VoucherStatus::Posted) {
            return false;
        }

        return match ($voucher->type) {
            VoucherType::Payment => $user->hasPermissionTo('payment.delete'),
            VoucherType::Receipt => $user->hasPermissionTo('receipt.delete'),
            default => false,
        };
    }

    /**
     * الاعتماد (Approval)
     */
    public function approve(User $user, Voucher $voucher): bool
    {
        if ($voucher->status === VoucherStatus::Posted) {
            return false;
        }

        return match ($voucher->type) {
            VoucherType::Payment => $user->hasPermissionTo('payment.approve'),
            VoucherType::Receipt => $user->hasPermissionTo('receipt.approve'),
            default => false,
        };
    }

    /**
     * الترحيل (Posting)
     */
    public function post(User $user, Voucher $voucher): bool
    {
        if ($voucher->status === VoucherStatus::Posted) {
            return false;
        }

        return match ($voucher->type) {
            VoucherType::Payment => $user->hasPermissionTo('payment.post'),
            VoucherType::Receipt => $user->hasPermissionTo('receipt.post'),
            default => false,
        };
    }

    /**
     * إلغاء الترحيل (Unpost)
     * يعتمد على صلاحية التعديل
     * الشرط: أن يكون السند في حالة مرحل
     */
    public function unpost(User $user, Voucher $voucher): bool
    {
        if ($voucher->status !== VoucherStatus::Posted) {
            return false;
        }

        return match ($voucher->type) {
            VoucherType::Payment => $user->hasPermissionTo('payment.update'),
            VoucherType::Receipt => $user->hasPermissionTo('receipt.update'),
            default => false,
        };
    }
}