<?php

declare(strict_types=1);

namespace App\Modules\HR\Policies;

use App\Models\User;
use App\Modules\HR\Models\InternshipApplication;
use Illuminate\Auth\Access\HandlesAuthorization;

class InternshipApplicationPolicy
{
    use HandlesAuthorization;

    /**
     * صلاحية استعراض كافة طلبات التدريب الخارجية المعلقة (فتح الشاشة)
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('hr.internship_applications.view');
    }

    /**
     * صلاحية عرض تفاصيل طلب تدريب معين داخل الشاشة
     */
    public function view(User $user, InternshipApplication $application): bool
    {
        return $user->hasPermissionTo('hr.internship_applications.view');
    }

    /**
     * صلاحية اعتماد وقبول طلب التدريب وتحويله لمتدرب (ربط فوري بصلاحية الشاشة الموحدة)
     */
    public function approve(User $user, InternshipApplication $application): bool
    {
        return $user->hasPermissionTo('hr.internship_applications.view');
    }

    /**
     * صلاحية رفض طلب التدريب المقدم (ربط فوري بصلاحية الشاشة الموحدة)
     */
    public function reject(User $user, InternshipApplication $application): bool
    {
        return $user->hasPermissionTo('hr.internship_applications.view');
    }
}
