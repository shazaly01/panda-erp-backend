<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PurchasingPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guardName = 'api';
        $moduleKey = 'purchasing';
        $moduleDisplayName = 'إدارة المشتريات والموردين';

        $permissionsData = [
            'requisitions' => [
                'title' => 'طلبات الشراء الداخلية',
                'actions' => [
                    'view' => 'عرض',
                    'create' => 'إضافة',
                    'update' => 'تعديل',
                    'delete' => 'حذف',
                    'approve' => 'اعتماد الطلب',
                    'reject' => 'رفض الطلب',
                ]
            ],
            'orders' => [
                'title' => 'أوامر الشراء المعتمدة',
                'actions' => [
                    'view' => 'عرض',
                    'create' => 'إضافة',
                    'update' => 'تعديل',
                    'delete' => 'حذف',
                    'confirm' => 'تأكيد واعتماد أمر الشراء',
                    'cancel' => 'إلغاء أمر الشراء',
                ]
            ],
            'receipts' => [
                'title' => 'سندات استلام البضائع المخزنية',
                'actions' => [
                    'view' => 'عرض',
                    'create' => 'إضافة سند استلام',
                    'update' => 'تعديل',
                    'delete' => 'حذف',
                    'receive' => 'تأكيد الاستلام والترحيل المخزني',
                    'cancel' => 'إلغاء سند الاستلام',
                ]
            ],
            'bills' => [
                'title' => 'فواتير المشتريات المالية',
                'actions' => [
                    'view' => 'عرض',
                    'create' => 'إضافة فاتورة',
                    'update' => 'تعديل',
                    'delete' => 'حذف',
                    'post' => 'ترحيل الفاتورة وتوليد القيد المحاسبي',
                    'cancel' => 'إلغاء الفاتورة',
                ]
            ],
            'returns' => [
                'title' => 'مرتجعات المشتريات والإشعارات المدينة',
                'actions' => [
                    'view' => 'عرض',
                    'create' => 'إضافة مرتجع',
                    'update' => 'تعديل',
                    'delete' => 'حذف',
                    'post' => 'ترحيل المرتجع وعكس الأثر المالي والمخزني',
                    'cancel' => 'إلغاء المرتجع',
                ]
            ],
            'reports' => [
                'title' => 'تقارير وتحليلات المشتريات',
                'actions' => [
                    'summary' => 'عرض التقرير التجميعي للمشتريات',
                    'supplier_purchases' => 'عرض تقرير مشتريات الموردين',
                    'order_tracking' => 'تتبع أوامر الشراء ومطابقة التوريد والفوترة',
                    'pending_receipts' => 'تقرير بضائع المشتريات المعلقة قيد الاستلام',
                    'pending_bills' => 'تقرير فواتير المشتريات المستحقة وغير المسددة',
                    'price_history' => 'تحليل سجل تطور أسعار شراء الأصناف',
                ]
            ],
        ];

        $permissionsObjects = [];

        foreach ($permissionsData as $groupKey => $groupData) {
            foreach ($groupData['actions'] as $actionKey => $displayName) {
                $permissionName = "purchasing.{$groupKey}.{$actionKey}";

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

        // 1. دور مدير المشتريات (Purchasing Manager) - يمتلك كامل الصلاحيات والاعتمادات والتقارير
        $purchasingManagerRole = Role::firstOrCreate(['name' => 'Purchasing Manager', 'guard_name' => $guardName]);
        $purchasingManagerRole->syncPermissions($permissionsObjects);

        // 2. دور مسؤول المشتريات (Purchasing Officer) - صلاحيات التشغيل التنفيذية وإنشاء المستندات
        $purchasingOfficerRole = Role::firstOrCreate(['name' => 'Purchasing Officer', 'guard_name' => $guardName]);
        $purchasingOfficerRole->syncPermissions([
            'purchasing.requisitions.view',
            'purchasing.requisitions.create',
            'purchasing.requisitions.update',
            'purchasing.orders.view',
            'purchasing.orders.create',
            'purchasing.orders.update',
            'purchasing.receipts.view',
            'purchasing.receipts.create',
            'purchasing.receipts.update',
            'purchasing.bills.view',
            'purchasing.bills.create',
            'purchasing.bills.update',
            'purchasing.returns.view',
            'purchasing.returns.create',
            'purchasing.returns.update',
            'purchasing.reports.summary',
            'purchasing.reports.supplier_purchases',
            'purchasing.reports.order_tracking',
            'purchasing.reports.pending_receipts',
            'purchasing.reports.pending_bills',
            'purchasing.reports.price_history',
        ]);

        // 3. دور طالب الشراء (Purchasing Requester) - صلاحيات إنشاء ومتابعة طلبات الشراء الداخلية فقط
        $purchasingRequesterRole = Role::firstOrCreate(['name' => 'Purchasing Requester', 'guard_name' => $guardName]);
        $purchasingRequesterRole->syncPermissions([
            'purchasing.requisitions.view',
            'purchasing.requisitions.create',
            'purchasing.requisitions.update',
        ]);
    }
}