<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class InventoryPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guardName = 'api';
        $moduleKey = 'inventory';
        $moduleDisplayName = 'إدارة المخازن والمخزون';

        $permissionsData = [
            'units' => [
                'title' => 'وحدات القياس',
                'actions' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف']
            ],
            'categories' => [
                'title' => 'تصنيفات المنتجات',
                'actions' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف']
            ],
            'warehouses' => [
                'title' => 'المستودعات والمخازن',
                'actions' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف']
            ],
            'price_lists' => [
                'title' => 'قوائم الأسعار',
                'actions' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف']
            ],
            'products' => [
                'title' => 'المنتجات والأصناف',
                'actions' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف']
            ],
            'batches' => [
                'title' => 'التشغيلات والباتشات',
                'actions' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف']
            ],
            'stocks' => [
                'title' => 'أرصدة المخزون والكميات',
                'actions' => ['view' => 'عرض', 'manage' => 'إدارة وتحكم']
            ],
            'movements' => [
                'title' => 'الحركات المخزنية',
                'actions' => ['view' => 'عرض سجل الحركات', 'create' => 'إعادة تسجيل حركة', 'export' => 'تصدير التقارير']
            ],
            'transfers' => [
                'title' => 'التحويلات بين المخازن',
                'actions' => ['view' => 'عرض', 'create' => 'إضافة طلب تحويل', 'update' => 'تعديل', 'delete' => 'حذف', 'approve' => 'اعتماد وتأكيد التحويل']
            ],
            'adjustments' => [
                'title' => 'التسويات والجرد المخزني',
                'actions' => ['view' => 'عرض', 'create' => 'إضافة تسوية/جرد', 'update' => 'تعديل', 'delete' => 'حذف', 'approve' => 'اعتماد التسوية المالية والمخزنية']
            ],
            'boms' => [
                'title' => 'قوائم المكونات والتجميع (BOM)',
                'actions' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف']
            ],
            'production_orders' => [
                'title' => 'أوامر الإنتاج والتصنيع',
                'actions' => ['view' => 'عرض', 'create' => 'إضافة أمر إنتاج', 'update' => 'تعديل', 'delete' => 'حذف', 'approve' => 'اعتماد وتنفيذ الإنتاج']
            ],
            'reports' => [
                'title' => 'تقارير والرقابة على المخزون',
                'actions' => [
                    'stock_card' => 'عرض كارت الصنف التفصيلي',
                    'stock_balance' => 'عرض أرصدة وتقييم المخزون',
                    'integrity_audit' => 'فحص تدقيق ومطابقة البيانات المخزنية',
                    'discrepancies' => 'عرض تقارير فروقات وتسويات الجرد',
                    'transfers_tracking' => 'عرض تقارير تتبع التحويلات',
                    'batch_expiry' => 'عرض تقارير الصلاحيات والتشغيلات',
                    'serial_tracking' => 'عرض تقارير الأرقام التسلسلية',
                    'reorder_alerts' => 'عرض تقارير نواقص المخزون وإعادة الطلب',
                    'production_variance' => 'عرض تقارير انحرافات وتكاليف الإنتاج',
                ]
            ],
        ];

        $permissionsObjects = [];

        foreach ($permissionsData as $groupKey => $groupData) {
            foreach ($groupData['actions'] as $actionKey => $displayName) {
                $permissionName = "inventory.{$groupKey}.{$actionKey}";

                $permissionsObjects[] = Permission::updateOrCreate(
                    ['name' => $permissionName, 'guard_name' => $guardName],
                    [
                        'module' => $moduleKey,
                        'module_display_name' => $moduleDisplayName,
                        'group_name' => $groupKey,
                        'group_display_name' => $groupData['title'],
                        'action_name' => $actionKey,
                        'display_name' => $displayName
                    ]
                );
            }
        }

        // 1. دور مدير المخازن (Inventory Manager) - يمتلك كامل الصلاحيات بما فيها التدقيق الرقابي
        $inventoryManagerRole = Role::firstOrCreate(['name' => 'Inventory Manager', 'guard_name' => $guardName]);
        $inventoryManagerRole->syncPermissions($permissionsObjects);

        // 2. دور أمين المخزن (Inventory Officer) - صلاحيات التشغيل والتقارير التنفيذية
        $inventoryOfficerRole = Role::firstOrCreate(['name' => 'Inventory Officer', 'guard_name' => $guardName]);
        $inventoryOfficerRole->syncPermissions([
            'inventory.units.view',
            'inventory.categories.view',
            'inventory.warehouses.view',
            'inventory.price_lists.view',
            'inventory.products.view',
            'inventory.products.create',
            'inventory.products.update',
            'inventory.batches.view',
            'inventory.batches.create',
            'inventory.stocks.view',
            'inventory.movements.view',
            'inventory.transfers.view',
            'inventory.transfers.create',
            'inventory.transfers.update',
            'inventory.transfers.approve',
            'inventory.adjustments.view',
            'inventory.adjustments.create',
            'inventory.adjustments.update',
            'inventory.boms.view',
            'inventory.production_orders.view',
            'inventory.production_orders.create',
            'inventory.production_orders.update',
            'inventory.reports.stock_card',
            'inventory.reports.stock_balance',
            'inventory.reports.discrepancies',
            'inventory.reports.transfers_tracking',
            'inventory.reports.batch_expiry',
            'inventory.reports.serial_tracking',
            'inventory.reports.reorder_alerts',
            'inventory.reports.production_variance',
        ]);

        // 3. دور عامل/مُدخل بيانات المخزن (Inventory Worker) - صلاحيات الاستعلام وكارت الصنف فقط
        $inventoryWorkerRole = Role::firstOrCreate(['name' => 'Inventory Worker', 'guard_name' => $guardName]);
        $inventoryWorkerRole->syncPermissions([
            'inventory.products.view',
            'inventory.stocks.view',
            'inventory.movements.view',
            'inventory.transfers.view',
            'inventory.transfers.create',
            'inventory.reports.stock_card',
        ]);
    }
}