<?php

declare(strict_types=1);

namespace App\Modules\Sales\Policies;

use App\Models\User;
use App\Modules\Sales\Models\PosShift;

class PosShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('sales.shifts.view');
    }

    public function view(User $user, PosShift $posShift): bool
    {
        return $user->hasPermissionTo('sales.shifts.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('sales.shifts.open');
    }

    public function close(User $user, PosShift $posShift): bool
    {
        return $user->hasPermissionTo('sales.shifts.close') && $posShift->status === 'open';
    }

    public function reconcile(User $user, PosShift $posShift): bool
    {
        return $user->hasPermissionTo('sales.shifts.reconcile');
    }
}