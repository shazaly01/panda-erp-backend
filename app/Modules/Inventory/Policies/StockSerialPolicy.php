<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Policies;

use App\Models\User;
use App\Modules\Inventory\Models\StockSerial;
use Illuminate\Auth\Access\HandlesAuthorization;

class StockSerialPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('inventory.serials.view');
    }

    public function view(User $user, ?StockSerial $serial = null): bool
    {
        return $user->hasPermissionTo('inventory.serials.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('inventory.serials.create');
    }

    public function update(User $user, ?StockSerial $serial = null): bool
    {
        return $user->hasPermissionTo('inventory.serials.update');
    }

    public function delete(User $user, ?StockSerial $serial = null): bool
    {
        return $user->hasPermissionTo('inventory.serials.delete');
    }

    public function restore(User $user, ?StockSerial $serial = null): bool
    {
        return $user->hasPermissionTo('inventory.serials.delete');
    }

    public function forceDelete(User $user, ?StockSerial $serial = null): bool
    {
        return $user->hasPermissionTo('inventory.serials.delete');
    }
}