<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class InventoryReportPolicy
{
    use HandlesAuthorization;

    /**
     * 1. صلاحية عرض كارت الصنف التفصيلي
     */
    public function viewStockCard(User $user): bool
    {
        return $user->hasPermissionTo('inventory.reports.stock_card', 'api');
    }

    /**
     * 2. صلاحية عرض أرصدة وتقييم المخزون اللحظي
     */
    public function viewStockBalance(User $user): bool
    {
        return $user->hasPermissionTo('inventory.reports.stock_balance', 'api');
    }

    /**
     * 3. صلاحية فحص تدقيق ومطابقة البيانات المخزنية
     */
    public function viewIntegrityAudit(User $user): bool
    {
        return $user->hasPermissionTo('inventory.reports.integrity_audit', 'api');
    }

    /**
     * 4. صلاحية عرض تقارير فروقات وتسويات الجرد
     */
    public function viewDiscrepancies(User $user): bool
    {
        return $user->hasPermissionTo('inventory.reports.discrepancies', 'api');
    }

    /**
     * 5. صلاحية عرض تقارير تتبع التحويلات بين المخازن
     */
    public function viewTransfersTracking(User $user): bool
    {
        return $user->hasPermissionTo('inventory.reports.transfers_tracking', 'api');
    }

    /**
     * 6. صلاحية عرض تقارير الصلاحيات والتشغيلات
     */
    public function viewBatchExpiry(User $user): bool
    {
        return $user->hasPermissionTo('inventory.reports.batch_expiry', 'api');
    }

    /**
     * 7. صلاحية عرض تقارير الأرقام التسلسلية
     */
    public function viewSerialTracking(User $user): bool
    {
        return $user->hasPermissionTo('inventory.reports.serial_tracking', 'api');
    }

    /**
     * 8. صلاحية عرض تقارير نواقص المخزون وإعادة الطلب
     */
    public function viewReorderAlerts(User $user): bool
    {
        return $user->hasPermissionTo('inventory.reports.reorder_alerts', 'api');
    }

    /**
     * 9. صلاحية عرض تقارير انحرافات وتكاليف الإنتاج
     */
    public function viewProductionVariance(User $user): bool
    {
        return $user->hasPermissionTo('inventory.reports.production_variance', 'api');
    }
}