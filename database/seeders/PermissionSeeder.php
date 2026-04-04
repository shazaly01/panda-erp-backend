<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
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

        // --- قائمة الصلاحيات الجديدة للمشروع ---
        $permissions = [
            'dashboard.view',

            'user.view', 'user.create', 'user.update', 'user.delete',
            'role.view', 'role.create', 'role.update', 'role.delete',

            'setting.view', 'setting.update',
            'backup.view', 'backup.create', 'backup.delete', 'backup.download',

        ];

        // إنشاء الصلاحيات مع تحديد الحارس
        foreach ($permissions as $permission) {
            Permission::create([
                'name' => $permission,
                'guard_name' => $guardName,
            ]);
        }

        // --- إنشاء الأدوار الجديدة ---

        // 1. إنشاء دور "Super Admin"
        // هذا الدور يحصل على كل الصلاحيات تلقائيًا عبر AuthServiceProvider (Gate::before)
        // لذلك لا نعطيه صلاحيات هنا.
        Role::create([
            'name' => 'Super Admin',
            'guard_name' => $guardName,
        ]);

        // 2. إنشاء دور "Admin" (مدير النظام)
        $adminRole = Role::create([
            'name' => 'Admin',
            'guard_name' => $guardName,
        ]);
        // إعطاء دور "Admin" كل الصلاحيات
        $adminRole->givePermissionTo(Permission::where('guard_name', $guardName)->get());


        // 3. إنشاء دور "Data Entry" (مدخل بيانات)
        $dataEntryRole = Role::create([
            'name' => 'Data Entry',
            'guard_name' => $guardName,
        ]);
        // إعطاء دور "مدخل بيانات" صلاحيات العرض والإنشاء والتحديث فقط
        $dataEntryPermissions = Permission::where('guard_name', $guardName)
                                      ->where(function ($query) {
                                          $query->where('name', 'like', '%.view')
                                                ->orWhere('name', 'like', '%.create')
                                                ->orWhere('name', 'like', '%.update');
                                      })->pluck('name');
        $dataEntryRole->givePermissionTo($dataEntryPermissions);


        // 4. إنشاء دور "Auditor" (مراجع / مشاهد فقط)
        $auditorRole = Role::create([
            'name' => 'Auditor',
            'guard_name' => $guardName,
        ]);
        // إعطاء دور "مراجع" صلاحيات العرض فقط
        $auditorPermissions = Permission::where('guard_name', 'api')
                                      ->where('name', 'like', '%.view')
                                       ->where('name', '!=', 'dashboard.view')
                                      ->pluck('name');
        $auditorRole->givePermissionTo($auditorPermissions);
    }
}
