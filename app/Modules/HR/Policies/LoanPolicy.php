<?php

declare(strict_types=1);

namespace App\Modules\HR\Policies;

use App\Models\User;
use App\Modules\HR\Models\Loan;
use Illuminate\Auth\Access\HandlesAuthorization;

class LoanPolicy
{
    use HandlesAuthorization;

    public function before(User $user, $ability)
    {
        if ($user->hasRole('HR Manager')) {
            return true;
        }
    }

    /**
     * عرض قائمة السلف
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('hr.loans.view') || $user->hasPermissionTo('hr.loans.request');
    }

    /**
     * عرض تفاصيل سلفة محددة
     */
    public function view(User $user, Loan $loan): bool
    {
        return $user->hasPermissionTo('hr.loans.view') || $user->employee_id === $loan->employee_id;
    }

    /**
     * تقديم طلب سلفة
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('hr.loans.request');
    }

    /**
     * تعديل طلب السلفة
     */
    public function update(User $user, Loan $loan): bool
    {
        if ($user->hasPermissionTo('hr.loans.manage')) {
            return true;
        }

        // يُمنع تعديل السلفة نهائياً إذا تم اعتمادها أو صرفها
        return $user->employee_id === $loan->employee_id && $loan->status === 'pending';
    }

    /**
     * إلغاء/حذف طلب السلفة
     */
    public function delete(User $user, Loan $loan): bool
    {
        return $user->employee_id === $loan->employee_id && $loan->status === 'pending';
    }

    /**
     * اعتماد طلب السلفة إدارياً
     */
    public function approve(User $user, Loan $loan): bool
    {
        return $user->hasPermissionTo('hr.loans.approve');
    }
}
