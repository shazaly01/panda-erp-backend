<?php

declare(strict_types=1);

namespace App\Modules\HR\Policies;

use App\Models\User;
use App\Modules\HR\Models\Shift;
use Illuminate\Auth\Access\HandlesAuthorization;

class ShiftPolicy
{
    use HandlesAuthorization;

    /**
     * السماح لمدير الموارد البشرية بتجاوز جميع الصلاحيات
     */
    public function before(User $user, $ability)
    {
        if ($user->hasRole('HR Manager')) {
            return true;
        }
    }

    /**
     * عرض قائمة الورديات المُعرفة في النظام
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('hr.shifts.view');
    }

    /**
     * عرض تفاصيل وردية محددة
     */
    public function view(User $user, Shift $shift): bool
    {
        return $user->hasPermissionTo('hr.shifts.view');
    }

    /**
     * إنشاء وردية جديدة (مثال: تعريف وردية ليلية جديدة)
     * أو تعيين موظف على وردية
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('hr.shifts.create');
    }

    /**
     * تعديل إعدادات الوردية (مثل تغيير وقت الدخول أو فترة السماح)
     */
    public function update(User $user, Shift $shift): bool
    {
        return $user->hasPermissionTo('hr.shifts.update');
    }

    /**
     * حذف الوردية (يجب التأكد لاحقاً في الـ Controller أنه لا يوجد موظفين مرتبطين بها قبل الحذف)
     */
    public function delete(User $user, Shift $shift): bool
    {
        return $user->hasPermissionTo('hr.shifts.delete');
    }
}
