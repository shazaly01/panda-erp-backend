<?php

declare(strict_types=1);

namespace App\Modules\Sales\Policies;

use App\Models\User;
use App\Modules\Sales\Models\SalesInvoice;

class SalesInvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('sales.invoices.view');
    }

    public function view(User $user, SalesInvoice $invoice): bool
    {
        return $user->hasPermissionTo('sales.invoices.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('sales.invoices.create');
    }

    public function update(User $user, SalesInvoice $invoice): bool
    {
        return $user->hasPermissionTo('sales.invoices.update')
            && $invoice->status === 'draft'
            && $invoice->posting_status === 'unposted';
    }

    public function delete(User $user, SalesInvoice $invoice): bool
    {
        return $user->hasPermissionTo('sales.invoices.delete')
            && $invoice->status === 'draft'
            && $invoice->posting_status === 'unposted';
    }

    public function confirm(User $user, SalesInvoice $invoice): bool
    {
        return $user->hasPermissionTo('sales.invoices.confirm')
            && $invoice->status === 'draft';
    }

    public function cancel(User $user, SalesInvoice $invoice): bool
    {
        return $user->hasPermissionTo('sales.invoices.cancel')
            && $invoice->status !== 'cancelled'
            && $invoice->posting_status === 'unposted';
    }

    public function post(User $user, SalesInvoice $invoice): bool
    {
        return $user->hasPermissionTo('sales.invoices.post')
            && $invoice->status === 'confirmed'
            && $invoice->posting_status === 'unposted';
    }

    public function applyDiscount(User $user): bool
    {
        return $user->hasPermissionTo('sales.invoices.apply_discount');
    }

    public function print(User $user, SalesInvoice $invoice): bool
    {
        return $user->hasPermissionTo('sales.invoices.print');
    }
}