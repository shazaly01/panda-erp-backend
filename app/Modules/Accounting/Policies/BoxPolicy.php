<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Policies;

use App\Models\User;
use App\Modules\Accounting\Models\Box;
use Illuminate\Auth\Access\HandlesAuthorization;

class BoxPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('box.view');
    }

    public function view(User $user, Box $box): bool
    {
        // يمكن إضافة شرط هنا: هل الموظف مسموح له رؤية هذه الخزينة تحديداً؟
        // حالياً سنكتفي بالصلاحية العامة
        return $user->can('box.view');
    }

    public function create(User $user): bool
    {
        return $user->can('box.create');
    }

    public function update(User $user, Box $box): bool
    {
        return $user->can('box.update');
    }

    public function delete(User $user, Box $box): bool
    {
        return $user->can('box.delete');
    }
}
