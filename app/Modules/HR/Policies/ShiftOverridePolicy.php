<?php

declare(strict_types=1);

namespace App\Modules\HR\Policies;

use App\Models\User;
use App\Modules\HR\Models\ShiftOverride;
use Illuminate\Auth\Access\HandlesAuthorization;

class ShiftOverridePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('hr.shift_overrides.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ShiftOverride $shiftOverride): bool
    {
        return $user->hasPermissionTo('hr.shift_overrides.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('hr.shift_overrides.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ShiftOverride $shiftOverride): bool
    {
        return $user->hasPermissionTo('hr.shift_overrides.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ShiftOverride $shiftOverride): bool
    {
        return $user->hasPermissionTo('hr.shift_overrides.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ShiftOverride $shiftOverride): bool
    {
        return $user->hasPermissionTo('hr.shift_overrides.delete');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ShiftOverride $shiftOverride): bool
    {
        return $user->hasPermissionTo('hr.shift_overrides.delete');
    }
}
