<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Policies;

use App\Models\User;
use App\Modules\Purchasing\Enums\RequisitionStatus;
use App\Modules\Purchasing\Models\PurchaseRequisition;
use Illuminate\Auth\Access\HandlesAuthorization;

class PurchaseRequisitionPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('purchasing.requisitions.view');
    }

    public function view(User $user, PurchaseRequisition $requisition): bool
    {
        return $user->hasPermissionTo('purchasing.requisitions.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('purchasing.requisitions.create');
    }

    public function update(User $user, PurchaseRequisition $requisition): bool
    {
        if (! $user->hasPermissionTo('purchasing.requisitions.update')) {
            return false;
        }

        // لا يمكن تعديل الطلب إلا إذا كان مسودة أو بانتظار الاعتماد
        return in_array($requisition->status, [
            RequisitionStatus::DRAFT,
            RequisitionStatus::PENDING_APPROVAL,
        ], true);
    }

    public function delete(User $user, PurchaseRequisition $requisition): bool
    {
        if (! $user->hasPermissionTo('purchasing.requisitions.delete')) {
            return false;
        }

        // الحذف مقتصر فقط على المسودات
        return $requisition->status === RequisitionStatus::DRAFT;
    }

    public function approve(User $user, PurchaseRequisition $requisition): bool
    {
        if (! $user->hasPermissionTo('purchasing.requisitions.approve')) {
            return false;
        }

        return $requisition->status === RequisitionStatus::PENDING_APPROVAL;
    }

    public function reject(User $user, PurchaseRequisition $requisition): bool
    {
        if (! $user->hasPermissionTo('purchasing.requisitions.reject')) {
            return false;
        }

        return $requisition->status === RequisitionStatus::PENDING_APPROVAL;
    }
}