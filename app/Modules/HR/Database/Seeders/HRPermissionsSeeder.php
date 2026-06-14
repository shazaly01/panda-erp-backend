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
        $moduleKey = 'hr';
        $moduleDisplayName = 'الموارد البشرية';

        // إعادة هيكلة المصفوفة لتشمل اسم الشاشة بالعربية (title) بجانب مصفوفة الأفعال (actions)
        $permissionsData = [
            'departments' => [
                'title' => 'الإدارات والأقسام',
                'actions' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف']
            ],
            'positions' => [
                'title' => 'الوظائف والمهن',
                'actions' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف']
            ],
            'employees' => [
                'title' => 'ملفات الموظفين',
                'actions' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف']
            ],
            'contracts' => [
                'title' => 'العقود والتوظيف',
                'actions' => ['view' => 'عرض', 'manage' => 'إدارة كاملة']
            ],
            'payroll' => [
                'title' => 'الرواتب والأجور',
                'actions' => ['view' => 'عرض', 'post' => 'ترحيل مالي']
            ],
            'settings' => [
                'title' => 'إعدادات الموارد البشرية',
                'actions' => ['manage' => 'إدارة كاملة']
            ],
            'shifts' => [
                'title' => 'الورديات وساعات العمل',
                'actions' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف']
            ],
            'working_schedules' => [
                'title' => 'جداول العمل الأسبوعية',
                'actions' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف']
            ],
            'calendar_exceptions' => [
                'title' => 'العطلات والإجازات الرسمية',
                'actions' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف']
            ],
            'shift_overrides' => [
                'title' => 'تجاوزات الورديات',
                'actions' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف']
            ],
            'attendance' => [
                'title' => 'الحضور والانصراف (كشك)',
                'actions' => ['view' => 'عرض', 'manage' => 'إدارة كاملة']
            ],
            'team_attendance' => [
                'title' => 'إدارة حضور الفريق',
                'actions' => ['manage' => 'إدارة الفريق']
            ],
            'leaves' => [
                'title' => 'طلبات الإجازات',
                'actions' => ['view' => 'عرض', 'manage' => 'إدارة كاملة', 'approve' => 'اعتماد', 'request' => 'تقديم طلب']
            ],
            'loans' => [
                'title' => 'السلف والقروض للموظفين',
                'actions' => ['view' => 'عرض', 'manage' => 'إدارة كاملة', 'approve' => 'اعتماد', 'request' => 'تقديم طلب']
            ],
            'payroll_inputs' => [
                'title' => 'المكافآت والجزاءات المباشرة',
                'actions' => ['view' => 'عرض', 'manage' => 'إدارة كاملة', 'approve' => 'اعتماد']
            ],
            'pay_groups' => [
                'title' => 'مجموعات وفئات الدفع',
                'actions' => ['view' => 'عرض']
            ],
            'pay_periods' => [
                'title' => 'فترات الدفع المالية',
                'actions' => ['view' => 'عرض']
            ],
            'overtime_policies' => [
                'title' => 'سياسات العمل الإضافي',
                'actions' => ['view' => 'عرض', 'manage' => 'إدارة كاملة']
            ],
            'internet_vouchers' => [
                'title' => 'كوبونات الإنترنت والشبكة',
                'actions' => ['view' => 'عرض']
            ],
            'hr_leave_passes' => [
                'title' => 'أذونات الخروج المؤقتة للموظفين',
                'actions' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف', 'approve' => 'اعتماد إداري', 'gate_check' => 'فحص حراسة البوابة']
            ],
            'hr_visitors' => [
                'title' => 'إدارة سجلات الزوار',
                'actions' => [
                    'view' => 'عرض',
                    'create' => 'إضافة (تسجيل مسبق)',
                    'update' => 'تعديل',
                    'delete' => 'حذف',
                    'check_in' => 'تسجيل دخول من البوابة',
                    'check_out' => 'تسجيل خروج من البوابة'
                ]
            ],
        ];

        $permissionsObjects = [];

        // إنشاء أو تحديث الصلاحيات وحفظ التراجم والموديولات في قاعدة البيانات مباشرة
        foreach ($permissionsData as $groupKey => $groupData) {
            foreach ($groupData['actions'] as $actionKey => $displayName) {

                // استثناء الحالات التي تملك مسمى موديول مدمج مسبقاً لحمايتها من تكرار البادئة
                // استثناء الحالات التي تملك مسمى موديول مدمج مسبقاً لحمايتها من تكرار البادئة
                if ($groupKey === 'internet_vouchers' || $groupKey === 'hr_leave_passes' || $groupKey === 'hr_visitors') {
                    $permissionName = "{$groupKey}.{$actionKey}";
                } else {
                    $permissionName = "hr.{$groupKey}.{$actionKey}";
                }

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

        // 1. دور مدير الموارد البشرية (HR Manager) - يمتلك كافة صلاحيات القسم
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
            'hr_leave_passes.view',
            'hr_leave_passes.create',
            'hr_leave_passes.update',
            'hr_leave_passes.delete',
            'hr_leave_passes.approve',
            'hr_leave_passes.gate_check',
            'hr_visitors.view',
            'hr_visitors.create',
            'hr_visitors.update',
            'hr_visitors.delete',
            'hr_visitors.check_in',
            'hr_visitors.check_out',
        ]);

        // 3. دور الموظف العادي (Employee)
        $employeeRole = Role::firstOrCreate(['name' => 'Employee', 'guard_name' => $guardName]);
        $employeeRole->syncPermissions(['hr.leaves.request', 'hr.loans.request']);
    }
}
