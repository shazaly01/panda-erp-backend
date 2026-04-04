<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Policies;

use App\Models\User;
use App\Modules\Accounting\Models\CostCenter;
use Illuminate\Auth\Access\HandlesAuthorization;

class CostCenterPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('cost_center.view');
    }

    public function view(User $user, CostCenter $costCenter): bool
    {
        return $user->can('cost_center.view');
    }

    public function create(User $user): bool
    {
        return $user->can('cost_center.create');
    }

    public function update(User $user, CostCenter $costCenter): bool
    {
        return $user->can('cost_center.update');
    }

    public function delete(User $user, CostCenter $costCenter): bool
    {
        return $user->can('cost_center.delete');
    }
}
