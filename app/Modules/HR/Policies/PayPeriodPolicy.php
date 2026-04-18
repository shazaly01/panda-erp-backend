<?php

namespace App\Modules\HR\Policies;

use App\Models\User;
use App\Modules\HR\Models\PayPeriod;

class PayPeriodPolicy
{
    /**
     * عرض قائمة الفترات المالية
     */
    public function viewAny(User $user): bool
    {
        return $user->can('hr.pay_periods.view');
    }

    /**
     * عرض تفاصيل فترة معينة
     */
    public function view(User $user, PayPeriod $payPeriod): bool
    {
        return $user->can('hr.pay_periods.view');
    }

    /**
     * إنشاء/توليد فترة مالية جديدة
     */
    public function create(User $user): bool
    {
        return $user->can('hr.pay_periods.manage');
    }

    /**
     * تعديل فترة مالية (مثل تغيير حالتها من مفتوحة إلى مغلقة)
     */
    public function update(User $user, PayPeriod $payPeriod): bool
    {
        return $user->can('hr.pay_periods.manage');
    }

    /**
     * حذف فترة مالية
     */
    public function delete(User $user, PayPeriod $payPeriod): bool
    {
        return $user->can('hr.pay_periods.manage');
    }
}
