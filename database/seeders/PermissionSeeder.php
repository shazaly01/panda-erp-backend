<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // إعادة تعيين الأدوار والصلاحيات المخزنة مؤقتاً (cache)
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // --- تعريف الحارس ---
        $guardName = 'api';

        // --- هيكلة الصلاحيات الأساسية للمشروع بالتقسيم الجديد ---
        $permissionsData = [
            'dashboard' => [
                'view' => 'رؤية الإحصائيات'
            ],
            'user' => [
                'view' => 'عرض',
                'create' => 'إضافة',
                'update' => 'تعديل',
                'delete' => 'حذف',
                'approve' => 'تفعيل الحسابات المعلقة'
            ],
            'role' => [
                'view' => 'عرض',
                'create' => 'إضافة',
                'update' => 'تعديل',
                'delete' => 'حذف'
            ],
            'setting' => [
                'view' => 'عرض',
                'update' => 'تعديل'
            ],
            'backup' => [
                'view' => 'عرض',
                'create' => 'إضافة',
                'delete' => 'حذف',
                'download' => 'تحميل'
            ],
            'grant_request' => [
                'view' => 'عرض',
                'create' => 'إضافة',
                'update' => 'تعديل',
                'delete' => 'حذف',
                'print' => 'طباعة الخطاب'
            ],
        ];

        // إنشاء أو تحديث الصلاحيات
        foreach ($permissionsData as $groupKey => $actions) {
            foreach ($actions as $actionKey => $displayName) {
                $permissionName = "{$groupKey}.{$actionKey}";

                Permission::updateOrCreate(
                    ['name' => $permissionName, 'guard_name' => $guardName],
                    [
                        'module' => $groupKey === 'grant_request' ? 'grant_requests' : 'system',
                        'group_name' => $groupKey,
                        'action_name' => $actionKey,
                        'display_name' => $displayName
                    ]
                );
            }
        }

        // --- إنشاء الأدوار وتحديث الصلاحيات (استخدام updateOrCreate لمنع التكرار) ---

        // 1. إنشاء دور "Super Admin"
        Role::updateOrCreate(
            ['name' => 'Super Admin', 'guard_name' => $guardName]
        );

        // 2. إنشاء دور "Admin" (مدير النظام)
        $adminRole = Role::updateOrCreate(
            ['name' => 'Admin', 'guard_name' => $guardName]
        );
        // إعطاء دور "Admin" كل الصلاحيات المسجلة حتى هذه اللحظة
        $adminRole->syncPermissions(Permission::where('guard_name', $guardName)->get());

        // 3. إنشاء دور "Data Entry" (مدخل بيانات)
        $dataEntryRole = Role::updateOrCreate(
            ['name' => 'Data Entry', 'guard_name' => $guardName]
        );
        // إعطاء دور "مدخل بيانات" صلاحيات العرض والإنشاء والتحديث والطباعة
        $dataEntryPermissions = Permission::where('guard_name', $guardName)
            ->whereIn('action_name', ['view', 'create', 'update', 'print'])
            ->get();
        $dataEntryRole->syncPermissions($dataEntryPermissions);

        // 4. إنشاء دور "Auditor" (مراجع / مشاهد فقط)
        $auditorRole = Role::updateOrCreate(
            ['name' => 'Auditor', 'guard_name' => $guardName]
        );
        // إعطاء دور "مراجع" صلاحيات العرض والطباعة فقط، باستثناء لوحة التحكم
        $auditorPermissions = Permission::where('guard_name', $guardName)
            ->whereIn('action_name', ['view', 'print'])
            ->where('name', '!=', 'dashboard.view')
            ->get();
        $auditorRole->syncPermissions($auditorPermissions);
    }
}