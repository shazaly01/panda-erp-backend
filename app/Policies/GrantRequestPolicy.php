<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\GrantRequest;
use App\Models\User;

class GrantRequestPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('grant_request.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, GrantRequest $grantRequest): bool
    {
        return $user->hasPermissionTo('grant_request.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('grant_request.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, GrantRequest $grantRequest): bool
    {
        return $user->hasPermissionTo('grant_request.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, GrantRequest $grantRequest): bool
    {
        return $user->hasPermissionTo('grant_request.delete');
    }

    /**
     * Determine whether the user can print the model.
     */
    public function print(User $user, GrantRequest $grantRequest): bool
    {
        return $user->hasPermissionTo('grant_request.print');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, GrantRequest $grantRequest): bool
    {
        return $user->hasPermissionTo('grant_request.delete');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, GrantRequest $grantRequest): bool
    {
        return $user->hasPermissionTo('grant_request.delete');
    }
}