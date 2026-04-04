<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Policies;

use App\Models\User;
use App\Modules\Accounting\Models\BankAccount;
use Illuminate\Auth\Access\HandlesAuthorization;

class BankAccountPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('bank_account.view');
    }

    public function view(User $user, BankAccount $bankAccount): bool
    {
        return $user->can('bank_account.view');
    }

    public function create(User $user): bool
    {
        return $user->can('bank_account.create');
    }

    public function update(User $user, BankAccount $bankAccount): bool
    {
        return $user->can('bank_account.update');
    }

    public function delete(User $user, BankAccount $bankAccount): bool
    {
        return $user->can('bank_account.delete');
    }
}
