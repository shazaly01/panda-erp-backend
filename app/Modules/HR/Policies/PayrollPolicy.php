<?php

namespace App\Modules\HR\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PayrollPolicy
{
    use HandlesAuthorization;

    /**
     * هل يحق للمستخدم عرض الرواتب والملخصات وتصدير الملفات؟
     */
    public function view(User $user): bool
    {
        return $user->can('hr.payroll.view');
    }

    /**
     * هل يحق للمستخدم عمل معاينة (Preview) لقسيمة الراتب؟
     */
    public function preview(User $user): bool
    {
        return $user->can('hr.payroll.view');
    }

    /**
     * هل يحق للمستخدم اعتماد وترحيل الرواتب للمحاسبة؟
     */
    public function postBatch(User $user): bool
    {
        return $user->can('hr.payroll.post');
    }
}
