<?php

namespace App\Services;

use App\Models\Permission;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class RoleService
{
    public function createRole(array $data): Role
    {
        return DB::transaction(function () use ($data) {
            $role = Role::create(['name' => $data['name'], 'guard_name' => 'api']);
            $role->syncPermissions($data['permissions'] ?? []);

            return $role->load('permissions');
        });
    }

    public function updateRole(Role $role, array $data): Role
    {
        return DB::transaction(function () use ($role, $data) {
            $role->update(['name' => $data['name']]);
            $role->syncPermissions($data['permissions'] ?? []);

            return $role->load('permissions');
        });
    }

    public function getStructuredPermissions(): array
    {
        $permissions = Permission::where('guard_name', 'api')->get();
        $grouped = $permissions->groupBy('group_name');

        $structuredGroups = [];
        $groupTranslations = $this->getGroupTranslations();

        foreach ($grouped as $groupKey => $groupPermissions) {
            if (empty($groupKey)) continue;

            $formattedPermissions = $groupPermissions->map(function ($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'action' => $p->action_name,
                    'action_display' => $p->display_name,
                ];
            })->values()->toArray();

            $structuredGroups[] = [
                'key' => $groupKey,
                'display_name' => $groupTranslations[$groupKey] ?? $groupKey,
                'permissions' => $formattedPermissions,
            ];
        }

        return [
            'groups' => $structuredGroups,
            'actions' => $this->getActionsList(),
        ];
    }

    private function getActionsList(): array
    {
        $actions = [
            'view'      => 'عرض',
            'create'    => 'إضافة',
            'update'    => 'تعديل',
            'delete'    => 'حذف',
            'manage'    => 'إدارة كاملة',
            'approve'   => 'اعتماد',
            'post'      => 'ترحيل مالي',
            'request'   => 'تقديم طلب',
            'close'     => 'إغلاق',
            'download'  => 'تحميل',
        ];

        $formattedActions = [];
        foreach ($actions as $key => $display) {
            $formattedActions[] = ['key' => $key, 'display' => $display];
        }

        return $formattedActions;
    }

    private function getGroupTranslations(): array
    {
        return [
            // المحاسبة
            'accounting'          => 'نظام المحاسبة عام',
            'dashboard'           => 'لوحة التحكم',
            'account'             => 'دليل الحسابات',
            'cost_center'         => 'مراكز التكلفة',
            'fiscal_year'         => 'السنوات المالية',
            'currency'            => 'العملات',
            'box'                 => 'الخزائن (الصناديق)',
            'bank_account'        => 'الحسابات البنكية',
            'payment'             => 'سندات الصرف',
            'receipt'             => 'سندات القبض',
            'journal_entry'       => 'القيود اليومية',
            'report'              => 'التقارير المالية',
            'accounting_settings' => 'إعدادات المحاسبة',

            'account_mapping'     => 'ربط وتوجيه الحسابات',
            'sequences'           => 'التسلسلات والترقيم',

            // الموارد البشرية
            'departments'         => 'الإدارات والأقسام',
            'positions'           => 'الوظائف والمهن',
            'employees'           => 'ملفات الموظفين',
            'contracts'           => 'العقود والتوظيف',
            'payroll'             => 'الرواتب والأجور',
            'shifts'              => 'الورديات',
            'working_schedules'   => 'جداول العمل',
            'calendar_exceptions' => 'العطلات والطوارئ',
            'shift_overrides'     => 'تجاوزات الورديات',
            'attendance'          => 'الحضور والانصراف',
            'team_attendance'     => 'إدارة حضور الفريق',
            'leaves'              => 'الإجازات',
            'loans'               => 'السلف والقروض',
            'payroll_inputs'      => 'المكافآت والجزاءات',
            'settings'            => 'إعدادات الموارد البشرية',

            // الإضافات المكتشفة
            'pay_groups'          => 'مجموعات الدفع',
            'pay_periods'         => 'فترات الدفع',
            'overtime_policies'   => 'سياسات العمل الإضافي',
            'internet_vouchers'   => 'كوبونات الإنترنت',

            // إدارة النظام
            'user'                => 'المستخدمين',
            'role'                => 'الأدوار والصلاحيات',
            'backup'              => 'النسخ الاحتياطي',
            'setting'             => 'إعدادات النظام',
        ];
    }
}
