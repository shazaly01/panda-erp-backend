<?php

declare(strict_types=1);

namespace App\Modules\Sales\Policies;

use App\Models\User;
use App\Modules\Sales\Models\SalesInvoicePayment;

class SalesInvoicePaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('sales.payments.view');
    }

    public function view(User $user, SalesInvoicePayment $payment): bool
    {
        return $user->hasPermissionTo('sales.payments.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('sales.payments.create');
    }

    public function update(User $user, SalesInvoicePayment $payment): bool
    {
        return $user->hasPermissionTo('sales.payments.update');
    }

    public function delete(User $user, SalesInvoicePayment $payment): bool
    {
        return $user->hasPermissionTo('sales.payments.delete');
    }
}