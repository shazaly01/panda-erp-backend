<?php

declare(strict_types=1);

namespace App\Modules\Sales\Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class SalesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guardName = 'api';
        $moduleKey = 'sales';
        $moduleDisplayName = 'إدارة المبيعات ونقاط البيع';

        $permissionsData = [
            'shifts' => [
                'title' => 'ورديات وجلسات الكاشير',
                'actions' => [
                    'view' => 'عرض الورديات',
                    'open' => 'فتح وردية جديدة',
                    'close' => 'إغلاق الوردية الحالية',
                    'reconcile' => 'تسوية وفحص الفروقات النقدية للدرج',
                ],
            ],
            'invoices' => [
                'title' => 'فواتير المبيعات',
                'actions' => [
                    'view' => 'عرض',
                    'create' => 'إضافة فاتورة',
                    'update' => 'تعديل الفاتورة',
                    'delete' => 'حذف الفاتورة',
                    'confirm' => 'تأكيد واعتماد الفاتورة',
                    'cancel' => 'إلغاء الفاتورة',
                    'post' => 'ترحيل الفاتورة وتوليد الأثر المالي والمخزني',
                    'apply_discount' => 'تطبيق خصم إضافي أو يدوي',
                    'print' => 'طباعة الفاتورة',
                ],
            ],
            'payments' => [
                'title' => 'مدفوعات وتحصيلات الفواتير',
                'actions' => [
                    'view' => 'عرض سجل المدفوعات',
                    'create' => 'تسجيل وتحصيل دفعة',
                    'delete' => 'إلغاء دفعة مسجلة',
                ],
            ],
            'returns' => [
                'title' => 'مرتجعات المبيعات والإشعارات الدائنة',
                'actions' => [
                    'view' => 'عرض',
                    'create' => 'إضافة مرتجع مبيعات',
                    'update' => 'تعديل المرتجع',
                    'delete' => 'حذف المرتجع',
                    'post' => 'ترحيل المرتجع وعكس الأثر المالي والمخزني',
                    'cancel' => 'إلغاء المرتجع',
                ],
            ],
            'pos' => [
                'title' => 'نقاط البيع السريعة والمطاعم (POS)',
                'actions' => [
                    'access' => 'الدخول لواجهة شاشة نقطة البيع',
                    'quick_sale' => 'البيع السريع المباشر',
                    'change_table' => 'تحديد أو تغيير الطاولة',
                    'split_bill' => 'تقسيم الحساب أو الفاتورة',
                ],
            ],
            'reports' => [
                'title' => 'تقارير وتحليلات المبيعات',
                'actions' => [
                    'summary' => 'عرض التقرير التجميعي للمبيعات',
                    'daily_sales' => 'تقرير المبيعات اليومية',
                    'cashier_shifts' => 'تقرير ملخص ورديات الكاشير',
                    'product_sales' => 'تقرير مبيعات الأصناف وربحيتها',
                    'customer_sales' => 'تقرير مبيعات وحركات العملاء',
                    'vat_report' => 'تقرير إقرار ضريبة القيمة المضافة للمبيعات',
                ],
            ],
        ];

        foreach ($permissionsData as $groupKey => $groupData) {
            foreach ($groupData['actions'] as $actionKey => $displayName) {
                $permissionName = "sales.{$groupKey}.{$actionKey}";

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