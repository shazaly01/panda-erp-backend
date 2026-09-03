<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Policies;

use App\Models\User;
use App\Modules\Accounting\Models\Budget;
use Illuminate\Auth\Access\HandlesAuthorization;

class BudgetPolicy
{
    use HandlesAuthorization;

    /**
     * التحقق من صلاحية عرض قائمة الموازنات
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('budget.view');
    }

    /**
     * التحقق من صلاحية عرض تفاصيل موازنة معينة
     */
    public function view(User $user, Budget $budget): bool
    {
        return $user->hasPermissionTo('budget.view');
    }

    /**
     * التحقق من صلاحية إنشاء موازنة جديدة
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('budget.create');
    }

    /**
     * التحقق من صلاحية تعديل الموازنة (يشترط أن تكون مسودة)
     */
    public function update(User $user, Budget $budget): bool
    {
        if (! $user->hasPermissionTo('budget.update')) {
            return false;
        }

        return $budget->isDraft();
    }

    /**
     * التحقق من صلاحية حذف الموازنة (يشترط أن تكون مسودة)
     */
    public function delete(User $user, Budget $budget): bool
    {
        if (! $user->hasPermissionTo('budget.delete')) {
            return false;
        }

        return $budget->isDraft();
    }

    /**
     * التحقق من صلاحية اعتماد الموازنة (يشترط أن تكون مسودة)
     */
    public function approve(User $user, Budget $budget): bool
    {
        if (! $user->hasPermissionTo('budget.approve')) {
            return false;
        }

        return $budget->isDraft();
    }

    /**
     * التحقق من صلاحية تفعيل الموازنة (يشترط أن تكون معتمدة)
     */
    public function activate(User $user, Budget $budget): bool
    {
        if (! $user->hasPermissionTo('budget.activate')) {
            return false;
        }

        return $budget->isApproved();
    }

    /**
     * التحقق من صلاحية إغلاق الموازنة (يشترط أن تكون معتمدة أو نشطة)
     */
    public function close(User $user, Budget $budget): bool
    {
        if (! $user->hasPermissionTo('budget.close')) {
            return false;
        }

        return $budget->isApproved() || $budget->isActive();
    }

    /**
     * التحقق من صلاحية عرض تقارير انحرافات الموازنة
     */
    public function viewVarianceReport(User $user, ?Budget $budget = null): bool
    {
        return $user->hasPermissionTo('report.budget_variance.view');
    }
}