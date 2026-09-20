<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
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
                ],
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
                ],
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
                ],
            ],
            'issues' => [
                'title' => 'أذونات صرف المواد المخزنية',
                'actions' => [
                    'view' => 'عرض أذونات الصرف',
                    'create' => 'إضافة إذن صرف',
                    'update' => 'تعديل إذن الصرف',
                    'delete' => 'حذف إذن الصرف',
                    'issue' => 'تأكيد الصرف والترحيل المخزني',
                    'cancel' => 'إلغاء إذن الصرف وعكس الحركة المخزنية',
                ],
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
                ],
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
                ],
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
                ],
            ],
        ];

        foreach ($permissionsData as $groupKey => $groupData) {
            foreach ($groupData['actions'] as $actionKey => $displayName) {
                $permissionName = "purchasing.{$groupKey}.{$actionKey}";

                Permission::updateOrCreate(
                    ['name' => $permissionName, 'guard_name' => $guardName],
                    [
                        'module' => $moduleKey,
                        'module_display_name' => $moduleDisplayName,
                        'group_name' => $groupKey,
                        'group_display_name' => $groupData['title'],
                        'action_name' => $actionKey,
                        'display_name' => $displayName,
                    ]
                );
            }
        }
    }
}