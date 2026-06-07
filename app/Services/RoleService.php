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
        // جلب كافة الصلاحيات الخاصة بالـ API من قاعدة البيانات
        $permissions = Permission::where('guard_name', 'api')->get();

        // 1. التجميع الأول: بناءً على الموديول (Module) لتوليد التبويبات الجانبية ديناميكياً
        $modulesGrouped = $permissions->groupBy('module');
        $structuredModules = [];

        foreach ($modulesGrouped as $moduleKey => $modulePermissions) {
            if (empty($moduleKey)) continue;

            // جلب الاسم العربي للموديول المخزن في قاعدة البيانات مباشرة من أول عنصر في التجميعة
            $moduleDisplayName = $modulePermissions->first()->module_display_name;
            if (empty($moduleDisplayName)) {
                $moduleDisplayName = $moduleKey === 'core' ? 'إدارة النظام' : $moduleKey;
            }

            // 2. التجميع الثاني: داخل الموديول الواحد، نجمع الصلاحيات حسب الشاشة (group_name)
            $groupsGrouped = $modulePermissions->groupBy('group_name');
            $structuredGroups = [];

            foreach ($groupsGrouped as $groupKey => $groupPermissions) {
                if (empty($groupKey)) continue;

                // جلب اسم الشاشة العربي المخزن في قاعدة البيانات مباشرة والذي تم بذره عبر الـ Seeder
                $groupDisplayName = $groupPermissions->first()->group_display_name;
                if (empty($groupDisplayName)) {
                    $groupDisplayName = $groupKey;
                }

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
                    'display_name' => $groupDisplayName,
                    'permissions' => $formattedPermissions,
                ];
            }

            $structuredModules[] = [
                'key' => $moduleKey,
                'display_name' => $moduleDisplayName,
                'groups' => $structuredGroups,
            ];
        }

        // إرجاع المصفوفة الهيكلية الجديدة لتقوم الواجهة الأمامية برسمها ذاتياً بدون تعقيد
        return [
            'modules' => $structuredModules,
            'actions' => $this->getActionsList(),
        ];
    }

    private function getActionsList(): array
    {
        $actions = [
            'view'       => 'عرض',
            'create'     => 'إضافة',
            'update'     => 'تعديل',
            'delete'     => 'حذف',
            'manage'     => 'إدارة كاملة',
            'approve'    => 'اعتماد',
            'post'       => 'ترحيل مالي',
            'request'    => 'تقديم طلب',
            'close'      => 'إغلاق',
            'download'   => 'تحميل',
            'gate_check' => 'فحص البوابة'
        ];

        $formattedActions = [];
        foreach ($actions as $key => $display) {
            $formattedActions[] = ['key' => $key, 'display' => $display];
        }

        return $formattedActions;
    }
}
