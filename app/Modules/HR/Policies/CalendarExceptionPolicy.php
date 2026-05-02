<?php

declare(strict_types=1);

namespace App\Modules\HR\Policies;

use App\Models\User;
use App\Modules\HR\Models\CalendarException;
use Illuminate\Auth\Access\HandlesAuthorization;

class CalendarExceptionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('hr.calendar_exceptions.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CalendarException $calendarException): bool
    {
        return $user->hasPermissionTo('hr.calendar_exceptions.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('hr.calendar_exceptions.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CalendarException $calendarException): bool
    {
        return $user->hasPermissionTo('hr.calendar_exceptions.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CalendarException $calendarException): bool
    {
        return $user->hasPermissionTo('hr.calendar_exceptions.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CalendarException $calendarException): bool
    {
        return $user->hasPermissionTo('hr.calendar_exceptions.delete');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CalendarException $calendarException): bool
    {
        return $user->hasPermissionTo('hr.calendar_exceptions.delete');
    }
}
