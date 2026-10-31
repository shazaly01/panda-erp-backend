<?php

declare(strict_types=1);

namespace App\Modules\Sales\Policies;

use App\Models\User;
use App\Modules\Sales\Models\SalesReturnPayment;

class SalesReturnPaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('sales.return_payments.view');
    }

    public function view(User $user, SalesReturnPayment $payment): bool
    {
        return $user->hasPermissionTo('sales.return_payments.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('sales.return_payments.create');
    }

    public function update(User $user, SalesReturnPayment $payment): bool
    {
        return $user->hasPermissionTo('sales.return_payments.update');
    }

    public function delete(User $user, SalesReturnPayment $payment): bool
    {
        return $user->hasPermissionTo('sales.return_payments.delete');
    }
}