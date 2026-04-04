<?php

namespace App\Modules\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AccountingPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. مسح الكاش للصلاحيات لضمان عدم وجود تضارب
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guardName = 'api'; // أو 'web' حسب إعدادات الـ Auth لديك

        // 2. قائمة الصلاحيات الكاملة
        $permissions = [
            // --- 1. General (عام) ---
            'accounting.view',       // الدخول للموديول بشكل عام
            'dashboard.view',        // رؤية الإحصائيات

            // --- 2. Master Data (البيانات الأساسية) ---

            // دليل الحسابات
            'account.view',
            'account.create',
            'account.update',
            'account.delete',

            // مراكز التكلفة (والفروع)
            'cost_center.view',
            'cost_center.create',
            'cost_center.update',
            'cost_center.delete',

            // السنوات المالية
            'fiscal_year.view',
            'fiscal_year.create',
            'fiscal_year.update',
            'fiscal_year.close', // صلاحية خاصة لإغلاق السنة

            // العملات (جديد)
            'currency.view',
            'currency.create',
            'currency.update',
            'currency.delete',

            // --- 3. Treasury Management (إدارة النقدية) ---

            // الخزائن (Boxes)
            'box.view',
            'box.create',
            'box.update',
            'box.delete',

            // الحسابات البنكية
            'bank_account.view',
            'bank_account.create',
            'bank_account.update',
            'bank_account.delete',

            // --- 4. Transactions (العمليات) ---

            // سندات الصرف (Payments)
            'payment.view',
            'payment.create',
            'payment.update',
            'payment.delete',
            'payment.approve', // [هام] الموافقة الإدارية قبل الترحيل
            'payment.post',    // الترحيل المالي (إنشاء القيد)

            // سندات القبض (Receipts)
            'receipt.view',
            'receipt.create',
            'receipt.update',
            'receipt.delete',
            'receipt.approve', // [هام] الموافقة الإدارية
            'receipt.post',    // الترحيل المالي

            // القيود اليومية (Journal Entries)
            'journal_entry.view',
            'journal_entry.create',
            'journal_entry.update',
            'journal_entry.delete',
            'journal_entry.post', // صلاحية الترحيل اليدوي للقيود

            // --- 5. Settings & Reports (الإعدادات والتقارير) ---

            // إعدادات الربط (توجيه الحسابات الافتراضي)
            'accounting_settings.view',
            'accounting_settings.update',

            // التقارير المالية
            'report.ledger',          // دفتر الأستاذ
            'report.trial_balance',   // ميزان المراجعة
            'report.income_statement',// قائمة الدخل
            'report.balance_sheet',   // الميزانية العمومية
            'report.daily_journal',   // دفتر اليومية
        ];

        // 3. إنشاء الصلاحيات في قاعدة البيانات
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => $guardName]);
        }

        // 4. تعيين الكل للمدير (Admin)
        $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => $guardName]);
        $adminRole->givePermissionTo($permissions);

        // 5. (اختياري) إنشاء دور "محاسب" بصلاحيات محدودة للتجربة
        $accountantRole = Role::firstOrCreate(['name' => 'Accountant', 'guard_name' => $guardName]);
        $accountantRole->givePermissionTo([
            'accounting.view',
            'dashboard.view',
            'payment.view', 'payment.create', 'payment.update', // لا يملك صلاحية Post أو Approve
            'receipt.view', 'receipt.create', 'receipt.update',
            'journal_entry.view', 'journal_entry.create',
            'account.view',
            'report.ledger',
        ]);
    }
}
