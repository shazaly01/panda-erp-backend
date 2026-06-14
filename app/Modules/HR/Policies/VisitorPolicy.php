<?php

declare(strict_types=1);

namespace App\Modules\HR\Policies;

use App\Models\User;
use App\Modules\HR\Models\Visitor;
use Illuminate\Auth\Access\HandlesAuthorization;

class VisitorPolicy
{
    use HandlesAuthorization;

    /**
     * استثناء لمدير الموارد البشرية (تخطي جميع الفحوصات إذا كان يملك دور HR Manager)
     */
    public function before(User $user, $ability)
    {
        if ($user->hasRole('HR Manager')) {
            return true;
        }
    }

    /**
     * هل يمكن للمستخدم عرض قائمة الزوار؟
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('hr_visitors.view');
    }

    /**
     * هل يمكن للمستخدم عرض بيانات زائر محدد؟
     */
    public function view(User $user, Visitor $visitor): bool
    {
        // يمكنه العرض إذا كان موظف يملك صلاحية العرض، أو إذا كان هو الموظف المستضيف للزيارة شخصياً
        return $user->hasPermissionTo('hr_visitors.view') || $user->employee_id === $visitor->employee_id;
    }

    /**
     * هل يمكن للمستخدم إنشاء سجل زائر جديد (من لوحة التحكم / الاستقبال)؟
     * ملاحظة: الرابط الخارجي العام للزوار سيتخطى هذا الفحص لأنه مسار عام (Public).
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('hr_visitors.create');
    }

    /**
     * هل يمكن للمستخدم تعديل بيانات زائر؟
     */
    public function update(User $user, Visitor $visitor): bool
    {
        // يمكن التعديل إذا كان يملك صلاحية التعديل، أو إذا كان هو الموظف المستضيف والزيارة ما زالت معلقة
        return $user->hasPermissionTo('hr_visitors.update') ||
            ($user->employee_id === $visitor->employee_id && $visitor->status === 'pending');
    }

    /**
     * هل يمكن للمستخدم حذف أو إلغاء سجل زيارة؟
     */
    public function delete(User $user, Visitor $visitor): bool
    {
        return $user->hasPermissionTo('hr_visitors.delete');
    }

    /**
     * هل يمكن لموظف الأمن أو الاستقبال تسجيل دخول الزائر عند البوابة؟
     */
    public function checkIn(User $user, Visitor $visitor): bool
    {
        // يجب أن يملك الصلاحية، وأن تكون حالة الزيارة تسمح بالدخول (pending)
        return $user->hasPermissionTo('hr_visitors.check_in') && $visitor->status === 'pending';
    }

    /**
     * هل يمكن لموظف الأمن أو الاستقبال تسجيل خروج الزائر عند البوابة؟
     */
    public function checkOut(User $user, Visitor $visitor): bool
    {
        // يجب أن يملك الصلاحية، وأن يكون الزائر داخل المنشأة بالفعل (checked_in)
        return $user->hasPermissionTo('hr_visitors.check_out') && $visitor->status === 'checked_in';
    }
}
