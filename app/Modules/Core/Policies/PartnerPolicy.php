<?php

declare(strict_types=1);

namespace App\Modules\Core\Policies;

use App\Models\User;
use App\Modules\Core\Models\Partner;
use Illuminate\Auth\Access\HandlesAuthorization;

class PartnerPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('core.partners.view');
    }

    public function view(User $user, Partner $partner): bool
    {
        return $user->hasPermissionTo('core.partners.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('core.partners.create');
    }

    public function update(User $user, Partner $partner): bool
    {
        return $user->hasPermissionTo('core.partners.update');
    }

    public function delete(User $user, Partner $partner): bool
    {
        return $user->hasPermissionTo('core.partners.delete');
    }
}