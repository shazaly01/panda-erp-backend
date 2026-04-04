<?php

namespace App\Modules\HR\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class HRPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // قائمة الصلاحيات المطلوبة
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
            'hr.payroll.post',      // اعتماد وصرف الرواتب

            // 4. إعدادات الرواتب
            'hr.settings.manage',      // تعديل القواعد والهياكل
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'api']);
        }

        // إنشاء دور "مدير موارد بشرية" للتجربة وإعطاؤه كل الصلاحيات
        $role = Role::firstOrCreate(['name' => 'HR Manager', 'guard_name' => 'api']);
        $role->givePermissionTo($permissions);
    }
}
