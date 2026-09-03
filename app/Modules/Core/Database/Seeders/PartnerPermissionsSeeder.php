<?php

declare(strict_types=1);

namespace App\Modules\Core\Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class PartnerPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guardName = 'api';
        $moduleKey = 'core';
        $moduleDisplayName = 'النظام العام والأساسي';

        $permissionsData = [
            'partners' => [
                'title' => 'جهات التعامل (العملاء والموردين)',
                'actions' => [
                    'view' => 'عرض جهات التعامل',
                    'create' => 'إضافة شريك جديد',
                    'update' => 'تعديل بيانات الشريك',
                    'delete' => 'حذف شريك',
                ]
            ],
        ];

        $permissionsObjects = [];

        foreach ($permissionsData as $groupKey => $groupData) {
            foreach ($groupData['actions'] as $actionKey => $displayName) {
                $permissionName = "core.{$groupKey}.{$actionKey}";

                $permissionsObjects[] = Permission::updateOrCreate(
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