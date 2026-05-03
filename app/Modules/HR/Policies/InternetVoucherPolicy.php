<?php

declare(strict_types=1);

namespace App\Modules\HR\Policies;

use App\Models\User;
use App\Modules\HR\Models\InternetVoucher;
use Illuminate\Auth\Access\HandlesAuthorization;

class InternetVoucherPolicy
{
    use HandlesAuthorization;

    /**
     * تحديد من يحق له استيراد الأكواد من الإكسيل
     */
    public function import(User $user): bool
    {
        return $user->hasPermissionTo('internet_vouchers.import');
    }

    // يمكننا إضافة باقي الصلاحيات (viewAny, view, create, delete) لاحقاً عند الحاجة لها
}
