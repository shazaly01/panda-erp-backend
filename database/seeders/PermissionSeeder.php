<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission; // تم التعديل لاستخدام المودل المخصص
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
                'view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف'
            ],
            'role' => [
                'view' => 'عرض', 'create' => 'إضافة', 'update' => 'تعديل', 'delete' => 'حذف'
            ],
            'setting' => [
                'view' => 'عرض', 'update' => 'تعديل'
            ],
            'backup' => [
                'view' => 'عرض', 'create' => 'إضافة', 'delete' => 'حذف', 'download' => 'تحميل'
            ],
        ];

        // إنشاء أو تحديث الصلاحيات
        foreach ($permissionsData as $groupKey => $actions) {
            foreach ($actions as $actionKey => $displayName) {
                $permissionName = "{$groupKey}.{$actionKey}";

                Permission::updateOrCreate(
                    ['name' => $permissionName, 'guard_name' => $guardName],
                    [
                        'module' => 'system',
                        'group_name' => $groupKey,
                        'action_name' => $actionKey,
                        'display_name' => $displayName
                    ]
                );
            }
        }

        // --- إنشاء الأدوار الجديدة (استخدام updateOrCreate لمنع التكرار) ---

        // 1. إنشاء دور "Super Admin"
        // هذا الدور يحصل على كل الصلاحيات تلقائيًا عبر ModuleServiceProvider (Gate::before)
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
        // إعطاء دور "مدخل بيانات" صلاحيات العرض والإنشاء والتحديث فقط (باستخدام الحقل الجديد action_name)
        $dataEntryPermissions = Permission::where('guard_name', $guardName)
            ->whereIn('action_name', ['view', 'create', 'update'])
            ->get();
        $dataEntryRole->syncPermissions($dataEntryPermissions);

        // 4. إنشاء دور "Auditor" (مراجع / مشاهد فقط)
        $auditorRole = Role::updateOrCreate(
            ['name' => 'Auditor', 'guard_name' => $guardName]
        );
        // إعطاء دور "مراجع" صلاحيات العرض فقط، باستثناء لوحة التحكم
        $auditorPermissions = Permission::where('guard_name', $guardName)
            ->where('action_name', 'view')
            ->where('name', '!=', 'dashboard.view')
            ->get();
        $auditorRole->syncPermissions($auditorPermissions);
    }
}
