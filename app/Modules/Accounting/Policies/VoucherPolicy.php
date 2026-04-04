<?php

namespace App\Modules\Accounting\Policies;

use App\Models\User; // أو مسار اليوزر الخاص بك
use App\Modules\Accounting\Models\Voucher;
use App\Modules\Accounting\Enums\VoucherType;
use App\Modules\Accounting\Enums\VoucherStatus;
use Illuminate\Auth\Access\HandlesAuthorization;

class VoucherPolicy
{
    use HandlesAuthorization;

    /**
     * تجاوز الصلاحيات للأدمن (اختياري)
     */
    public function before(User $user, $ability)
    {
        if ($user->hasRole('Admin')) { // إذا كنت تستخدم Spatie Roles
            return true;
        }
    }

    /**
     * عرض القائمة
     */
    public function viewAny(User $user): bool
    {
        // يسمح له إذا كان يملك صلاحية عرض السندات أو القبض
        return $user->can('payment.view') || $user->can('receipt.view');
    }

    /**
     * عرض سند محدد
     */
    public function view(User $user, Voucher $voucher): bool
    {
        return match ($voucher->type) {
            VoucherType::Payment => $user->can('payment.view'),
            VoucherType::Receipt => $user->can('receipt.view'),
            default => false,
        };
    }

    /**
     * إنشاء سند جديد
     * ملاحظة: التحقق من النوع يتم عادة في الكنترولر لأن السند لم ينشأ بعد
     */
    public function create(User $user): bool
    {
        return $user->can('payment.create') || $user->can('receipt.create');
    }

    /**
     * تعديل السند
     * الشرط: (يملك الصلاحية) + (السند في حالة مسودة أو مرفوض)
     */
    public function update(User $user, Voucher $voucher): bool
    {
        // لا يمكن تعديل سند مرحل أو ملغي
        if ($voucher->status === VoucherStatus::Posted || $voucher->status === VoucherStatus::Void) {
            return false;
        }

        return match ($voucher->type) {
            VoucherType::Payment => $user->can('payment.update'),
            VoucherType::Receipt => $user->can('receipt.update'),
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
            return false; // ممنوع حذف السندات المرحلة نهائياً
        }

        return match ($voucher->type) {
            VoucherType::Payment => $user->can('payment.delete'),
            VoucherType::Receipt => $user->can('receipt.delete'),
            default => false,
        };
    }

    /**
     * [جديد] الاعتماد (Approval)
     */
    public function approve(User $user, Voucher $voucher): bool
    {
        // لا يمكن اعتماد سند مرحل أصلاً
        if ($voucher->status === VoucherStatus::Posted) {
            return false;
        }

        return match ($voucher->type) {
            VoucherType::Payment => $user->can('payment.approve'),
            VoucherType::Receipt => $user->can('receipt.approve'),
            default => false,
        };
    }

    /**
     * [جديد] الترحيل (Posting)
     * تحويل السند إلى قيد محاسبي
     */
    public function post(User $user, Voucher $voucher): bool
    {
        // لا يمكن ترحيل سند مرحل مسبقاً
        if ($voucher->status === VoucherStatus::Posted) {
            return false;
        }

        // يجب أن يكون السند معتمداً أولاً (إذا كنت تطبق دورة الاعتماد)
        // if ($voucher->status !== VoucherStatus::Approved) { return false; }

        return match ($voucher->type) {
            VoucherType::Payment => $user->can('payment.post'),
            VoucherType::Receipt => $user->can('receipt.post'),
            default => false,
        };
    }
}
