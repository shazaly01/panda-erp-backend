<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Permission;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class RoleService
{
    public function createRole(array $data): Role
    {
        return DB::transaction(function () use ($data) {
            $role = Role::create(['name' => $data['name'], 'guard_name' => 'api']);
            $role->syncPermissions($data['permissions'] ?? []);

            return $role->load('permissions');
        });
    }

    public function updateRole(Role $role, array $data): Role
    {
        return DB::transaction(function () use ($role, $data) {
            $role->update(['name' => $data['name']]);
            $role->syncPermissions($data['permissions'] ?? []);

            return $role->load('permissions');
        });
    }

    public function getStructuredPermissions(): array
    {
        // جلب كافة الصلاحيات الخاصة بالـ API من قاعدة البيانات
        $permissions = Permission::where('guard_name', 'api')->get();

        // 1. التجميع الأول: بناءً على الموديول (Module) لتوليد التبويبات الجانبية ديناميكياً
        $modulesGrouped = $permissions->groupBy('module');
        $structuredModules = [];

        foreach ($modulesGrouped as $moduleKey => $modulePermissions) {
            if (empty($moduleKey)) {
                continue;
            }

            // جلب الاسم العربي للموديول المخزن في قاعدة البيانات مباشرة من أول عنصر في التجميعة
            $moduleDisplayName = $modulePermissions->first()->module_display_name;
            if (empty($moduleDisplayName)) {
                $moduleDisplayName = match ($moduleKey) {
                    'core', 'system' => 'إدارة النظام',
                    'grant_requests' => 'طلبات المنح والدعم',
                    'inventory'      => 'إدارة المخازن والمخزون',
                    'purchasing'     => 'إدارة المشتريات والموردين',
                    default          => $moduleKey,
                };
            }

            // 2. التجميع الثاني: داخل الموديول الواحد، نجمع الصلاحيات حسب الشاشة (group_name)
            $groupsGrouped = $modulePermissions->groupBy('group_name');
            $structuredGroups = [];

            foreach ($groupsGrouped as $groupKey => $groupPermissions) {
                if (empty($groupKey)) {
                    continue;
                }

                // جلب اسم الشاشة العربي المخزن في قاعدة البيانات مباشرة
                $groupDisplayName = $groupPermissions->first()->group_display_name;
                if (empty($groupDisplayName)) {
                    $groupDisplayName = match ($groupKey) {
                        'grant_request' => 'طلبات الدعم والمنح',
                        default         => $groupKey,
                    };
                }

                $formattedPermissions = $groupPermissions->map(function ($p) {
                    return [
                        'id'             => $p->id,
                        'name'           => $p->name,
                        'action'         => $p->action_name,
                        'action_display' => $p->display_name,
                    ];
                })->values()->toArray();

                $structuredGroups[] = [
                    'key'          => $groupKey,
                    'display_name' => $groupDisplayName,
                    'permissions'  => $formattedPermissions,
                ];
            }

            $structuredModules[] = [
                'key'          => $moduleKey,
                'display_name' => $moduleDisplayName,
                'groups'       => $structuredGroups,
            ];
        }

        // إرجاع المصفوفة الهيكلية لتتمكن الواجهة الأمامية من رسمها ديناميكياً
        return [
            'modules' => $structuredModules,
            'actions' => $this->getActionsList($permissions),
        ];
    }

    private function getActionsList($permissions = null): array
    {
        $actions = [
            'view'                => 'عرض',
            'create'              => 'إضافة',
            'update'              => 'تعديل',
            'delete'              => 'حذف',
            'print'               => 'طباعة',
            'manage'              => 'إدارة كاملة',
            'approve'             => 'اعتماد',
            'reject'              => 'رفض',
            'confirm'             => 'تأكيد واعتماد',
            'cancel'              => 'إلغاء',
            'receive'             => 'تأكيد الاستلام',
            'issue'               => 'تأكيد الصرف',
            'post'                => 'ترحيل مالي',
            'export'              => 'تصدير التقارير',
            'request'             => 'تقديم طلب',
            'close'               => 'إغلاق',
            'download'            => 'تحميل',
            'gate_check'          => 'فحص البوابة',
            'convert'             => 'تثبيت كـ موظف',
            'view_pending'        => 'عرض المعلقة',
            'view_active'         => 'عرض النشطة',
            'view_completed'      => 'عرض المكتملة',
            'view_rejected'       => 'عرض المرفوضة',
            'toggle_status'       => 'تغيير حالة التقديم',
            'check_in'            => 'تسجيل دخول زائر',
            'check_out'           => 'تسجيل خروج زائر',
            // صلاحيات تقارير المخازن
            'stock_card'          => 'كارت الصنف التفصيلي',
            'stock_balance'       => 'أرصدة وتقييم المخزون',
            'integrity_audit'     => 'فحص تدقيق ومطابقة البيانات',
            'discrepancies'       => 'فروقات وتسويات الجرد',
            'transfers_tracking'  => 'تتبع التحويلات',
            'batch_expiry'        => 'الصلاحيات والتشغيلات',
            'serial_tracking'     => 'الأرقام التسلسلية',
            'reorder_alerts'      => 'نواقص المخزون وإعادة الطلب',
            'production_variance' => 'انحرافات وتكاليف الإنتاج',
            // صلاحيات تقارير المشتريات
            'summary'             => 'التقرير التجميعي',
            'supplier_purchases'  => 'مشتريات الموردين',
            'order_tracking'      => 'تتبع أوامر الشراء',
            'pending_receipts'    => 'بضائع معلقة قيد الاستلام',
            'pending_bills'       => 'فواتير مستحقة وغير مسددة',
            'price_history'       => 'سجل تطور أسعار الشراء',
        ];

        // دمج تلقائي لأي إجراء موجود في قاعدة البيانات لتفادي سقوط أي صلاحية مستقبلاً
        if ($permissions !== null) {
            foreach ($permissions as $perm) {
                if (!empty($perm->action_name) && !isset($actions[$perm->action_name])) {
                    $actions[$perm->action_name] = $perm->display_name ?: $perm->action_name;
                }
            }
        }

        $formattedActions = [];
        foreach ($actions as $key => $display) {
            $formattedActions[] = ['key' => $key, 'display' => $display];
        }

        return $formattedActions;
    }
}