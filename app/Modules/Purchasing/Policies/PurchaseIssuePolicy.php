<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Policies;

use App\Models\User;
use App\Modules\Purchasing\Enums\IssueStatus;
use App\Modules\Purchasing\Models\PurchaseIssue;

class PurchaseIssuePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('purchasing.issues.view');
    }

    public function view(User $user, PurchaseIssue $issue): bool
    {
        return $user->hasPermissionTo('purchasing.issues.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('purchasing.issues.create');
    }

    public function update(User $user, PurchaseIssue $issue): bool
    {
        if ($issue->status !== IssueStatus::DRAFT) {
            return false;
        }

        return $user->hasPermissionTo('purchasing.issues.update');
    }

    public function delete(User $user, PurchaseIssue $issue): bool
    {
        if ($issue->status !== IssueStatus::DRAFT) {
            return false;
        }

        return $user->hasPermissionTo('purchasing.issues.delete');
    }

    public function confirm(User $user, PurchaseIssue $issue): bool
    {
        if ($issue->status !== IssueStatus::DRAFT) {
            return false;
        }

        return $user->hasPermissionTo('purchasing.issues.issue');
    }

    public function cancel(User $user, PurchaseIssue $issue): bool
    {
        if ($issue->status === IssueStatus::CANCELLED) {
            return false;
        }

        return $user->hasPermissionTo('purchasing.issues.cancel');
    }
}