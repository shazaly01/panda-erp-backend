<?php

namespace App\Modules\HR\Policies;

use App\Models\User;
use App\Modules\HR\Models\Contract;

class ContractPolicy
{
    /**
     * عرض عقد معين
     */
    public function view(User $user, Contract $contract): bool
    {
        // 1. الموظف يشاهد عقده الخاص
        // نحتاج للتأكد أن المستخدم مرتبط بموظف، وأن هذا الموظف هو صاحب العقد
        // ملاحظة: نفترض أنك أضفت علاقة employee() في موديل User
        if ($user->employee && $user->employee->id === $contract->employee_id) {
            return true;
        }

        // 2. أو لديه صلاحية مدير الرواتب
        return $user->hasPermissionTo('hr.contracts.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('hr.contracts.manage');
    }

    public function update(User $user, Contract $contract): bool
    {
        return $user->hasPermissionTo('hr.contracts.manage');
    }

    public function delete(User $user, Contract $contract): bool
    {
        return $user->hasPermissionTo('hr.contracts.manage');
    }
}
