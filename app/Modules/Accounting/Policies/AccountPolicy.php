<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Policies;

use App\Models\User;
use App\Modules\Accounting\Models\Account;
use Illuminate\Auth\Access\HandlesAuthorization;

class AccountPolicy
{
    use HandlesAuthorization;

    /**
     * هل يحق للمستخدم مشاهدة القائمة؟
     */
    public function viewAny(User $user): bool
    {
        return $user->can('account.view');
    }

    /**
     * هل يحق للمستخدم مشاهدة حساب معين؟
     */
    public function view(User $user, Account $account): bool
    {
        return $user->can('account.view');
    }

    /**
     * هل يحق للمستخدم إنشاء حساب جديد؟
     */
    public function create(User $user): bool
    {
        return $user->can('account.create');
    }

    /**
     * هل يحق للمستخدم تعديل الحساب؟
     */
    public function update(User $user, Account $account): bool
    {
        return $user->can('account.update');
    }

    /**
     * هل يحق للمستخدم حذف الحساب؟
     */
    public function delete(User $user, Account $account): bool
    {
        return $user->can('account.delete');
    }
}
