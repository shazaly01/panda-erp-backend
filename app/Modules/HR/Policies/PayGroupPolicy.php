<?php

namespace App\Modules\HR\Policies;

use App\Models\User;
use App\Modules\HR\Models\PayGroup;

class PayGroupPolicy
{
    /**
     * عرض قائمة مجموعات الدفع
     */
    public function viewAny(User $user): bool
    {
        return $user->can('hr.pay_groups.view');
    }

    /**
     * عرض تفاصيل مجموعة معينة
     */
    public function view(User $user, PayGroup $payGroup): bool
    {
        return $user->can('hr.pay_groups.view');
    }

    /**
     * إنشاء مجموعة دفع جديدة
     */
    public function create(User $user): bool
    {
        return $user->can('hr.pay_groups.manage');
    }

    /**
     * تعديل مجموعة الدفع
     */
    public function update(User $user, PayGroup $payGroup): bool
    {
        return $user->can('hr.pay_groups.manage');
    }

    /**
     * حذف أو إيقاف مجموعة الدفع
     */
    public function delete(User $user, PayGroup $payGroup): bool
    {
        return $user->can('hr.pay_groups.manage');
    }
}
