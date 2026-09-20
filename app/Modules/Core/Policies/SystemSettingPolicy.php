<?php

declare(strict_types=1);

namespace App\Modules\Core\Policies;

use App\Models\User;
use App\Modules\Core\Models\SystemSetting;
use Illuminate\Auth\Access\HandlesAuthorization;

class SystemSettingPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('system_settings.view');
    }

    public function view(User $user, SystemSetting $systemSetting): bool
    {
        return $user->hasPermissionTo('system_settings.view');
    }

    public function update(User $user, SystemSetting $systemSetting): bool
    {
        return $user->hasPermissionTo('system_settings.update');
    }
}