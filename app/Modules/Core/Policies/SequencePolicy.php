<?php

declare(strict_types=1);

namespace App\Modules\Core\Policies;

use App\Models\User;
use App\Modules\Core\Models\Sequence;
use Illuminate\Auth\Access\HandlesAuthorization;

class SequencePolicy
{
    use HandlesAuthorization;

    /**
     * تحديد ما إذا كان المستخدم يستطيع عرض قائمة التسلسلات
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_sequences');
    }

    /**
     * تحديد ما إذا كان المستخدم يستطيع عرض تسلسل محدد
     */
    public function view(User $user, Sequence $sequence): bool
    {
        return $user->hasPermissionTo('view_sequences');
    }

    /**
     * تحديد ما إذا كان المستخدم يستطيع إنشاء تسلسل جديد
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_sequences');
    }

    /**
     * تحديد ما إذا كان المستخدم يستطيع تحديث التسلسل
     */
    public function update(User $user, Sequence $sequence): bool
    {
        return $user->hasPermissionTo('update_sequences');
    }

    /**
     * تحديد ما إذا كان المستخدم يستطيع حذف التسلسل
     */
    public function delete(User $user, Sequence $sequence): bool
    {
        return $user->hasPermissionTo('delete_sequences');
    }
}
