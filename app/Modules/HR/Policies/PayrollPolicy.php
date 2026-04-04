<?php

namespace App\Modules\HR\Policies;

use App\Modules\HR\Models\Employee; // أو الموديل الرئيسي للرواتب إذا وجد
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PayrollPolicy
{
    use HandlesAuthorization;

    /**
     * هل يحق للمستخدم فتح صفحة الرواتب أو استعراض السجل؟
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('hr.payroll.view');
    }

    /**
     * هل يحق للمستخدم حساب الرواتب (المعاينة)؟
     */
    public function create(User $user): bool
    {
        // عادة من يملك صلاحية العرض يحق له التجربة (Preview)،
        // أو يمكنك تخصيص صلاحية hr.payroll.calculate إذا أردت دقة أكثر
        return $user->hasPermissionTo('hr.payroll.view');
    }

    /**
     * هل يحق للمستخدم اعتماد وترحيل الرواتب للمحاسبة؟
     * (هذه هي الأخطر)
     */
    public function post(User $user): bool
    {
        return $user->hasPermissionTo('hr.payroll.post');
    }
}
