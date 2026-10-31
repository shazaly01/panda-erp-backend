<?php

declare(strict_types=1);

namespace App\Modules\Sales\Policies;

use App\Models\User;
use App\Modules\Sales\Models\SalesReturn;

class SalesReturnPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('sales.returns.view');
    }

    public function view(User $user, SalesReturn $salesReturn): bool
    {
        return $user->hasPermissionTo('sales.returns.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('sales.returns.create');
    }

    public function update(User $user, SalesReturn $salesReturn): bool
    {
        return $user->hasPermissionTo('sales.returns.update')
            && $salesReturn->status === 'draft'
            && $salesReturn->posting_status === 'unposted';
    }

    public function delete(User $user, SalesReturn $salesReturn): bool
    {
        return $user->hasPermissionTo('sales.returns.delete')
            && $salesReturn->status === 'draft'
            && $salesReturn->posting_status === 'unposted';
    }

    public function confirm(User $user, SalesReturn $salesReturn): bool
    {
        return $user->hasPermissionTo('sales.returns.confirm')
            && $salesReturn->status === 'draft';
    }

    public function post(User $user, SalesReturn $salesReturn): bool
    {
        return $user->hasPermissionTo('sales.returns.post')
            && $salesReturn->status === 'confirmed'
            && $salesReturn->posting_status === 'unposted';
    }

    public function cancel(User $user, SalesReturn $salesReturn): bool
    {
        return $user->hasPermissionTo('sales.returns.cancel')
            && $salesReturn->status !== 'cancelled'
            && $salesReturn->posting_status === 'unposted';
    }
}