<?php

namespace App\Modules\HR\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class HRPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // قائمة الصلاحيات المطلوبة (شاملة التجاوزات الفردية للورديات)
        $permissions = [
            // 1. الهيكل التنظيمي
            'hr.departments.view', 'hr.departments.create', 'hr.departments.update', 'hr.departments.delete',
            'hr.positions.view', 'hr.positions.create', 'hr.positions.update', 'hr.positions.delete',

            // 2. الموظفين
            'hr.employees.view',
            'hr.employees.create',
            'hr.employees.update',
            'hr.employees.delete',

            // 3. البيانات المالية والحساسة
            'hr.contracts.view',
            'hr.contracts.manage',
            'hr.payroll.view',
            'hr.payroll.post',

            // 4. إعدادات الرواتب
            'hr.settings.manage',

            // ---------------------------------------------------------
            // 5. الجدولة والحضور (الورديات والقوالب والطوارئ والتجاوزات)
            // ---------------------------------------------------------

            // الورديات الأساسية
            'hr.shifts.view',
            'hr.shifts.create',
            'hr.shifts.update',
            'hr.shifts.delete',

            // قوالب الجدولة (Working Schedules)
            'hr.working_schedules.view',
            'hr.working_schedules.create',
            'hr.working_schedules.update',
            'hr.working_schedules.delete',

            // الاستثناءات التقويمية والطوارئ (Calendar Exceptions)
            'hr.calendar_exceptions.view',
            'hr.calendar_exceptions.create',
            'hr.calendar_exceptions.update',
            'hr.calendar_exceptions.delete',

            // 🌟 التجاوزات الفردية وتبديل الورديات (Shift Overrides) 🌟
            'hr.shift_overrides.view',
            'hr.shift_overrides.create',
            'hr.shift_overrides.update',
            'hr.shift_overrides.delete',

            // الحضور والانصراف
            'hr.attendance.view',
            'hr.attendance.manage',

            // الإجازات
            'hr.leaves.view',
            'hr.leaves.manage',
            'hr.leaves.approve',
            'hr.leaves.request',

            // السلف
            'hr.loans.view',
            'hr.loans.manage',
            'hr.loans.approve',
            'hr.loans.request',

            // المكافآت والجزاءات
            'hr.payroll_inputs.view',
            'hr.payroll_inputs.manage',
            'hr.payroll_inputs.approve',
        ];

        // إنشاء الصلاحيات في قاعدة البيانات
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'api']);
        }

        // ---------------------------------------------------------
        // توزيع الأدوار (Roles) الافتراضية
        // ---------------------------------------------------------

        // 1. مدير الموارد البشرية
        $hrManagerRole = Role::firstOrCreate(['name' => 'HR Manager', 'guard_name' => 'api']);
        $hrManagerRole->givePermissionTo($permissions);

        // 2. موظف الموارد البشرية (صلاحيات تشغيلية)
        $hrOfficerRole = Role::firstOrCreate(['name' => 'HR Officer', 'guard_name' => 'api']);
        $hrOfficerRole->givePermissionTo([
            'hr.departments.view', 'hr.positions.view',
            'hr.employees.view', 'hr.employees.create', 'hr.employees.update',
            'hr.contracts.view',
            'hr.payroll.view',

            // صلاحيات الجدولة والطوارئ
            'hr.shifts.view',
            'hr.working_schedules.view',
            'hr.calendar_exceptions.view',

            // 🌟 منح موظف الـ HR صلاحية إدارة التجاوزات الفردية 🌟
            'hr.shift_overrides.view', 'hr.shift_overrides.create', 'hr.shift_overrides.update', 'hr.shift_overrides.delete',

            'hr.attendance.view', 'hr.attendance.manage',
            'hr.leaves.view', 'hr.leaves.manage',
            'hr.loans.view'
        ]);

        // 3. الموظف العادي
        $employeeRole = Role::firstOrCreate(['name' => 'Employee', 'guard_name' => 'api']);
        $employeeRole->givePermissionTo([
            'hr.leaves.request',
            'hr.loans.request',
        ]);
    }
}
