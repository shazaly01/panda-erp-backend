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

        // 2. هيكلة الصلاحيات لتتطابق تماماً مع Router الواجهة الأمامية
        $permissionsData = [
            'accounting' => ['view' => 'عرض عام'],
            'dashboard' => ['view' => 'رؤية الإحصائيات'],
            'account' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف'],
            'cost_center' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف'],
            'fiscal_year' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'close' => 'إغلاق'],
            'currency' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف'],
            'box' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف'],
            'bank_account' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف'],
            'payment' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف', 'approve' => 'اعتماد', 'post' => 'ترحيل مالي'],
            'receipt' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف', 'approve' => 'اعتماد', 'post' => 'ترحيل مالي'],
            'journal_entry' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف', 'post' => 'ترحيل مالي'],
            'accounting_settings' => ['view' => 'عرض', 'update' => 'تعديل'],

            // --- الإضافات المكتشفة من الـ Router ---
            'account_mapping' => ['view' => 'عرض', 'manage' => 'إدارة كاملة'],

            // --- تصحيح مسارات التقارير لتطابق الـ Router ---
            'report' => [
                'statement.view' => 'كشف الحساب',
                'trial_balance.view' => 'ميزان المراجعة',
                'income_statement.view' => 'قائمة الدخل',
                'balance_sheet.view' => 'الميزانية العمومية',
                'daily_journal.view' => 'دفتر اليومية'
            ],
        ];

        $permissionsObjects = [];

        // 3. إنشاء أو تحديث الصلاحيات المهيكلة
        foreach ($permissionsData as $groupKey => $actions) {
            foreach ($actions as $actionKey => $displayName) {
                $permissionName = "{$groupKey}.{$actionKey}";

                $permissionsObjects[] = Permission::updateOrCreate(
                    ['name' => $permissionName, 'guard_name' => $guardName],
                    [
                        'module' => 'accounting',
                        'group_name' => $groupKey,
                        'action_name' => $actionKey,
                        'display_name' => $displayName
                    ]
                );
            }
        }

        // 4. معالجة الصلاحية الشاذة view_sequences (لأنها لا تحتوي على بادئة .sequence)
        $permissionsObjects[] = Permission::updateOrCreate(
            ['name' => 'view_sequences', 'guard_name' => $guardName],
            [
                'module' => 'core',
                'group_name' => 'sequences',
                'action_name' => 'view',
                'display_name' => 'عرض'
            ]
        );

        // 5. تعيين الكل للمدير (Admin)
        $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => $guardName]);
        $adminRole->syncPermissions(Permission::where('guard_name', $guardName)->get());

        // 6. إنشاء دور "محاسب"
        $accountantRole = Role::firstOrCreate(['name' => 'Accountant', 'guard_name' => $guardName]);
        $accountantRole->syncPermissions([
            'accounting.view', 'dashboard.view', 'payment.view', 'payment.create', 'payment.update',
            'receipt.view', 'receipt.create', 'receipt.update', 'journal_entry.view', 'journal_entry.create',
            'account.view', 'report.statement.view', 'report.trial_balance.view'
        ]);
    }
}
