<?php

namespace App\Modules\HR\Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class HRPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guardName = 'api';

        // هيكلة الصلاحيات بالتقسيم الموحد لمنع التضارب
        $permissionsData = [
            'departments' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف'],
            'positions' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف'],
            'employees' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف'],
            'contracts' => ['view' => 'عرض', 'manage' => 'إدارة كاملة'],
            'payroll' => ['view' => 'عرض', 'post' => 'ترحيل مالي'],
            'settings' => ['manage' => 'إدارة كاملة'],
            'shifts' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف'],
            'working_schedules' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف'],
            'calendar_exceptions' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف'],
            'shift_overrides' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف'],
            'attendance' => ['view' => 'عرض', 'manage' => 'إدارة كاملة'],
            'team_attendance' => ['manage' => 'إدارة الفريق'],
            'leaves' => ['view' => 'عرض', 'manage' => 'إدارة كاملة', 'approve' => 'اعتماد', 'request' => 'تقديم طلب'],
            'loans' => ['view' => 'عرض', 'manage' => 'إدارة كاملة', 'approve' => 'اعتماد', 'request' => 'تقديم طلب'],
            'payroll_inputs' => ['view' => 'عرض', 'manage' => 'إدارة كاملة', 'approve' => 'اعتماد'],
            'pay_groups' => ['view' => 'عرض'],
            'pay_periods' => ['view' => 'عرض'],
            'overtime_policies' => ['view' => 'عرض', 'manage' => 'إدارة كاملة'],
            'internet_vouchers' => ['view' => 'عرض'],

            // صلاحيات أذونات الخروج المؤقتة المربوطة باسم الجدول مباشرة لمنع تداخل البوادئ
            'hr_leave_passes' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف', 'approve' => 'اعتماد إداري', 'gate_check' => 'فحص حراسة البوابة']
        ];

        $permissionsObjects = [];

        // إنشاء أو تحديث الصلاحيات في قاعدة البيانات
        foreach ($permissionsData as $groupKey => $actions) {
            foreach ($actions as $actionKey => $displayName) {
                // استثناء الحالات التي تملك مسمى موديول مدمج مسبقاً لحمايتها من تكرار البادئة
                if ($groupKey === 'internet_vouchers' || $groupKey === 'hr_leave_passes') {
                    $permissionName = "{$groupKey}.{$actionKey}";
                } else {
                    $permissionName = "hr.{$groupKey}.{$actionKey}";
                }

                $permissionsObjects[] = Permission::updateOrCreate(
                    ['name' => $permissionName, 'guard_name' => $guardName],
                    [
                        'module' => 'hr',
                        'group_name' => $groupKey,
                        'action_name' => $actionKey,
                        'display_name' => $displayName
                    ]
                );
            }
        }

        // 1. دور مدير الموارد البشرية (HR Manager) - يمتلك كافة الصلاحيات
        $hrManagerRole = Role::firstOrCreate(['name' => 'HR Manager', 'guard_name' => $guardName]);
        $hrManagerRole->syncPermissions($permissionsObjects);

        // 2. دور موظف الموارد البشرية والعمليات (HR Officer)
        $hrOfficerRole = Role::firstOrCreate(['name' => 'HR Officer', 'guard_name' => $guardName]);
        $hrOfficerRole->syncPermissions([
            'hr.departments.view',
            'hr.positions.view',
            'hr.employees.view',
            'hr.employees.create',
            'hr.employees.update',
            'hr.contracts.view',
            'hr.payroll.view',
            'hr.shifts.view',
            'hr.working_schedules.view',
            'hr.calendar_exceptions.view',
            'hr.shift_overrides.view',
            'hr.shift_overrides.create',
            'hr.shift_overrides.update',
            'hr.shift_overrides.delete',
            'hr.attendance.view',
            'hr.attendance.manage',
            'hr.leaves.view',
            'hr.leaves.manage',
            'hr.loans.view',

            // ربط الصلاحيات الجديدة كلياً لدور الموظف المسؤول والحراسة
            'hr_leave_passes.view',
            'hr_leave_passes.create',
            'hr_leave_passes.update',
            'hr_leave_passes.delete',
            'hr_leave_passes.approve',
            'hr_leave_passes.gate_check'
        ]);

        // 3. دور الموظف العادي (Employee)
        $employeeRole = Role::firstOrCreate(['name' => 'Employee', 'guard_name' => $guardName]);
        $employeeRole->syncPermissions(['hr.leaves.request', 'hr.loans.request']);
    }
}
