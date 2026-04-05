<?php

namespace App\Modules\HR\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class HRPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // قائمة الصلاحيات المطلوبة (القديمة + الجديدة للتوسعات القادمة)
        $permissions = [
            // 1. الهيكل التنظيمي
            'hr.departments.view', 'hr.departments.create', 'hr.departments.update', 'hr.departments.delete',
            'hr.positions.view', 'hr.positions.create', 'hr.positions.update', 'hr.positions.delete',

            // 2. الموظفين
            'hr.employees.view',       // مشاهدة الملف الشخصي فقط
            'hr.employees.create',     // تعيين موظف
            'hr.employees.update',
            'hr.employees.delete',

            // 3. البيانات المالية والحساسة (هام جداً فصلها)
            'hr.contracts.view',       // مشاهدة العقود
            'hr.contracts.manage',     // إنشاء وتعديل العقود
            'hr.payroll.view',         // مشاهدة مسير الرواتب
            'hr.payroll.post',         // اعتماد وصرف الرواتب (ترحيل القيد)

            // 4. إعدادات الرواتب
            'hr.settings.manage',      // تعديل القواعد والهياكل

            // ---------------------------------------------------------
            // 5. الإضافات الجديدة (لخطة الـ ERP الشاملة)
            // ---------------------------------------------------------

            // الحضور والانصراف
            'hr.attendance.view',      // مشاهدة سجلات الحضور
            'hr.attendance.manage',    // تعديل السجلات يدوياً (اعتماد تأخيرات/إضافي)

            // الإجازات
            'hr.leaves.view',          // مشاهدة أرصدة وطلبات الإجازات
            'hr.leaves.manage',        // إدارة أرصدة الإجازات
            'hr.leaves.approve',       // اعتماد أو رفض طلب إجازة (للمدير)
            'hr.leaves.request',       // تقديم طلب إجازة (للموظف نفسه)

            // السلف
            'hr.loans.view',           // مشاهدة سجلات السلف
            'hr.loans.manage',         // إدارة وجدولة السلف
            'hr.loans.approve',        // اعتماد طلب سلفة
            'hr.loans.request',        // تقديم طلب سلفة (للموظف نفسه)
        ];

        // إنشاء الصلاحيات
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'api']);
        }

        // ---------------------------------------------------------
        // توزيع الأدوار (Roles) الافتراضية
        // ---------------------------------------------------------

        // 1. إنشاء دور "مدير موارد بشرية" وإعطاؤه كل الصلاحيات
        $hrManagerRole = Role::firstOrCreate(['name' => 'HR Manager', 'guard_name' => 'api']);
        $hrManagerRole->givePermissionTo($permissions);

        // 2. دور "موظف موارد بشرية" (صلاحيات تشغيلية، بدون صلاحيات الاعتماد المالي أو الترحيل)
        $hrOfficerRole = Role::firstOrCreate(['name' => 'HR Officer', 'guard_name' => 'api']);
        $hrOfficerRole->givePermissionTo([
            'hr.departments.view', 'hr.positions.view',
            'hr.employees.view', 'hr.employees.create', 'hr.employees.update',
            'hr.contracts.view',
            'hr.payroll.view', // يشاهد الرواتب لكن لا يرحلها
            'hr.attendance.view', 'hr.attendance.manage',
            'hr.leaves.view', 'hr.leaves.manage',
            'hr.loans.view'
        ]);

        // 3. دور "موظف عادي" (بوابة الخدمة الذاتية ESS - مشاهدة وطلب فقط)
        $employeeRole = Role::firstOrCreate(['name' => 'Employee', 'guard_name' => 'api']);
        $employeeRole->givePermissionTo([
            'hr.leaves.request',
            'hr.loans.request',
        ]);
    }
}
