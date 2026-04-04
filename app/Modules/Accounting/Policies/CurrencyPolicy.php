<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Policies;

use App\Models\User;
use App\Modules\Accounting\Models\Currency;
use Illuminate\Auth\Access\HandlesAuthorization;

class CurrencyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('currency.view');
    }

    public function view(User $user, Currency $currency): bool
    {
        return $user->can('currency.view');
    }

    public function create(User $user): bool
    {
        return $user->can('currency.create');
    }

    public function update(User $user, Currency $currency): bool
    {
        return $user->can('currency.update');
    }

    public function delete(User $user, Currency $currency): bool
    {
        return $user->can('currency.delete');
    }
}
