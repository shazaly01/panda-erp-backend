<?php

namespace App\Modules\HR\Policies;

use App\Models\User;
use App\Modules\HR\Models\Position;

class PositionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('hr.positions.view');
    }

    public function view(User $user, Position $position): bool
    {
        return $user->hasPermissionTo('hr.positions.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('hr.positions.create');
    }

    public function update(User $user, Position $position): bool
    {
        return $user->hasPermissionTo('hr.positions.update');
    }

    public function delete(User $user, Position $position): bool
    {
        return $user->hasPermissionTo('hr.positions.delete');
    }
}
