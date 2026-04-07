<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // 1. الصلاحيات والمستخدمين
            PermissionSeeder::class,
            \App\Modules\Accounting\Database\Seeders\AccountingPermissionsSeeder::class,
            UserSeeder::class,

            // 2. الإعدادات الأساسية (تسلسل الأرقام)
            CoreConfigurationSeeder::class,

            // ==========================================
            // 3. التأسيس المحاسبي للموارد البشرية والـ ERP
            // ==========================================

            // أ. إنشاء شجرة الحسابات أولاً (الأساس)
            \App\Modules\Accounting\Database\Seeders\ChartOfAccountsSeeder::class,

            // ب. ربط قواعد الرواتب والعمليات بالحسابات (يعتمد على الخطوة السابقة)
            \App\Modules\Accounting\Database\Seeders\AccountMappingSeeder::class,

            // يمكنك إضافة seeders أخرى هنا لاحقاً
        ]);
    }
}
