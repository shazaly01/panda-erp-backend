<?php

declare(strict_types=1);

namespace App\Modules\HR\Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class HRPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guardName = 'api';
        $moduleKey = 'hr';
        $moduleDisplayName = 'الموارد البشرية';

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
                'title' => 'ملفات الموظفين والمتدربين',
                'actions' => ['view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف', 'convert' => 'تثبيت متدرب كموظف دائم']
            ],
            'internship_applications' => [
                'title' => 'إدارة برنامج وطلبات التدريب',
                'actions' => [
                    'view_pending' => 'عرض طلبات التقديم المعلقة',
                    'view_active' => 'عرض المتدربين النشطين',
                    'view_completed' => 'عرض المتدربين المنتهية فترتهم',
                    'view_rejected' => 'عرض الطلبات المرفوضة',
                    'toggle_status' => 'فتح وقفل استقبال طلبات التدريب',
                    'approve' => 'اعتماد وقبول المتدرب',
                    'reject' => 'رفض الطلب',
                    'delete' => 'حذف الطلب',
                ]
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

        foreach ($permissionsData as $groupKey => $groupData) {
            foreach ($groupData['actions'] as $actionKey => $displayName) {

                if ($groupKey === 'internet_vouchers' || $groupKey === 'hr_leave_passes' || $groupKey === 'hr_visitors') {
                    $permissionName = "{$groupKey}.{$actionKey}";
                } else {
                    $permissionName = "hr.{$groupKey}.{$actionKey}";
                }

                Permission::updateOrCreate(
                    ['name' => $permissionName, 'guard_name' => $guardName],
                    [
                        'module' => $moduleKey,
                        'module_display_name' => $moduleDisplayName,
                        'group_name' => $groupKey,
                        'group_display_name' => $groupData['title'],
                        'action_name' => $actionKey,
                        'display_name' => $displayName
                    ]
                );
            }
        }
    }
}