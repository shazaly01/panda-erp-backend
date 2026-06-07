<?php

namespace App\Modules\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AccountingPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guardName = 'api';
        $moduleKey = 'accounting';
        $moduleDisplayName = 'الحسابات والمالية';

        // إعادة هيكلة المصفوفة لتشمل اسم الشاشة بالعربية (title) ومصفوفة الأفعال (actions) بالتوجيه الجديد
        $permissionsData = [
            'accounting' => [
                'title' => 'نظام المحاسبة عام',
                'actions' => ['view' => 'عرض عام']
            ],
            'dashboard' => [
                'title' => 'لوحة التحكم والإحصائيات',
                'actions' => ['view' => 'رؤية الإحصائيات']
            ],
            'account' => [
                'title' => 'دليل الحسابات',
                'actions' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف']
            ],
            'cost_center' => [
                'title' => 'مراكز التكلفة',
                'actions' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف']
            ],
            'fiscal_year' => [
                'title' => 'السنوات المالية',
                'actions' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'close' => 'إغلاق']
            ],
            'currency' => [
                'title' => 'العملات',
                'actions' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف']
            ],
            'box' => [
                'title' => 'الخزائن (الصناديق)',
                'actions' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف']
            ],
            'bank_account' => [
                'title' => 'الحسابات البنكية',
                'actions' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف']
            ],
            'payment' => [
                'title' => 'سندات الصرف',
                'actions' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف', 'approve' => 'اعتماد', 'post' => 'ترحيل مالي']
            ],
            'receipt' => [
                'title' => 'سندات القبض',
                'actions' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف', 'approve' => 'اعتماد', 'post' => 'ترحيل مالي']
            ],
            'journal_entry' => [
                'title' => 'القيود اليومية',
                'actions' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف', 'post' => 'ترحيل مالي']
            ],
            'accounting_settings' => [
                'title' => 'إعدادات المحاسبة',
                'actions' => ['view' => 'عرض', 'update' => 'تعديل']
            ],
            'account_mapping' => [
                'title' => 'ربط وتوجيه الحسابات',
                'actions' => ['view' => 'عرض', 'manage' => 'إدارة كاملة']
            ],
            'report' => [
                'title' => 'التقارير المالية',
                'actions' => [
                    'statement.view' => 'كشف الحساب',
                    'trial_balance.view' => 'ميزان المراجعة',
                    'income_statement.view' => 'قائمة الدخل',
                    'balance_sheet.view' => 'الميزانية العمومية',
                    'daily_journal.view' => 'دفتر اليومية'
                ]
            ],
        ];

        $permissionsObjects = [];

        // إنشاء أو تحديث الصلاحيات وحفظ التراجم والموديولات في قاعدة البيانات مباشرة
        foreach ($permissionsData as $groupKey => $groupData) {
            foreach ($groupData['actions'] as $actionKey => $displayName) {
                $permissionName = "{$groupKey}.{$actionKey}";

                $permissionsObjects[] = Permission::updateOrCreate(
                    ['name' => $permissionName, 'guard_name' => $guardName],
                    [
                        'module' => $moduleKey,
                        'module_display_name' => $moduleDisplayName,
                        'group_name' => $groupKey,
                        'group_display_name' => $groupData['title'], // 🌟 الحفظ المباشر لاسم الشاشة بالعربية
                        'action_name' => $actionKey,
                        'display_name' => $displayName
                    ]
                );
            }
        }

        // معالجة الصلاحية الشاذة view_sequences وضبطها لتتبع موديول إدارة النظام تلقائياً وببنية البيانات الجديدة
        $permissionsObjects[] = Permission::updateOrCreate(
            ['name' => 'view_sequences', 'guard_name' => $guardName],
            [
                'module' => 'core',
                'module_display_name' => 'إدارة النظام',
                'group_name' => 'sequences',
                'group_display_name' => 'التسلسلات والترقيم', // 🌟 اسم الشاشة بالعربية
                'action_name' => 'view',
                'display_name' => 'عرض'
            ]
        );

        // تعيين الكل للمدير (Admin)
        $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => $guardName]);
        $adminRole->syncPermissions(Permission::where('guard_name', $guardName)->get());

        // إنشاء دور "محاسب" وتعيين صلاحياته الأساسية بنمط مسميات الصلاحيات اللاتينية
        $accountantRole = Role::firstOrCreate(['name' => 'Accountant', 'guard_name' => $guardName]);
        $accountantRole->syncPermissions([
            'accounting.view', 'dashboard.view', 'payment.view', 'payment.create', 'payment.update',
            'receipt.view', 'receipt.create', 'receipt.update', 'journal_entry.view', 'journal_entry.create',
            'account.view', 'report.statement.view', 'report.trial_balance.view'
        ]);
    }
}
