<?php

declare(strict_types=1);

namespace App\Modules\HR\Policies;

use App\Models\User;
use App\Modules\HR\Models\ViolationType;
use Illuminate\Auth\Access\HandlesAuthorization;

class ViolationTypePolicy
{
    use HandlesAuthorization;

    /**
     * تجاوز صلاحيات مدير الموارد البشرية على مستوى الموديول
     * ملاحظة: تجاوز الـ Super Admin العام يتم مركزياً عبر Gate::before
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('HR Manager')) {
            return true;
        }

        return null;
    }

    /**
     * عرض قائمة اللوائح وأنواع المخالفات (لمسؤولي الإعدادات أو المشرفين المخوّلين بتسجيل مخالفات)
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('hr.violation_types.view')
            || $user->hasPermissionTo('hr.settings.manage')
            || $user->hasPermissionTo('hr.disciplinary_actions.create');
    }

    /**
     * عرض تفاصيل لائحة محددة وسلم تدرجها العقابي
     */
    public function view(User $user, ViolationType $violationType): bool
    {
        return $user->hasPermissionTo('hr.violation_types.view')
            || $user->hasPermissionTo('hr.settings.manage')
            || $user->hasPermissionTo('hr.disciplinary_actions.create');
    }

    /**
     * إنشاء لائحة مخالفة جديدة
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('hr.violation_types.create')
            || $user->hasPermissionTo('hr.settings.manage');
    }

    /**
     * تعديل لائحة مخالفة أو درجات سلمها العقابي
     */
    public function update(User $user, ViolationType $violationType): bool
    {
        return $user->hasPermissionTo('hr.violation_types.update')
            || $user->hasPermissionTo('hr.settings.manage');
    }

    /**
     * حذف / أرشفة لائحة مخالفة
     */
    public function delete(User $user, ViolationType $violationType): bool
    {
        return $user->hasPermissionTo('hr.violation_types.delete')
            || $user->hasPermissionTo('hr.settings.manage');
    }
}