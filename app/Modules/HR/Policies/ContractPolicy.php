<?php

namespace App\Modules\HR\Policies;

use App\Models\User;
use App\Modules\HR\Models\Contract;

class ContractPolicy
{
    /**
     * عرض قائمة العقود (لشاشة الـ Data Table)
     */
    public function viewAny(User $user): bool
    {
        return $user->can('hr.contracts.view');
    }

    /**
     * عرض عقد معين
     */
    public function view(User $user, Contract $contract): bool
    {
        // 1. الموظف يشاهد عقده الخاص
        // تأكد من وجود علاقة employee في موديل User
        if ($user->employee && $user->employee->id === $contract->employee_id) {
            return true;
        }

        // 2. أو لديه صلاحية مدير/موظف الرواتب
        return $user->can('hr.contracts.view');
    }

    /**
     * إنشاء عقد جديد
     */
    public function create(User $user): bool
    {
        return $user->can('hr.contracts.manage');
    }

    /**
     * تعديل العقد
     */
    public function update(User $user, Contract $contract): bool
    {
        return $user->can('hr.contracts.manage');
    }

    /**
     * حذف أو أرشفة العقد
     */
    public function delete(User $user, Contract $contract): bool
    {
        return $user->can('hr.contracts.manage');
    }
}
