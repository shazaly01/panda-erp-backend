<?php

declare(strict_types=1);

namespace App\Modules\Core\Database\Seeders;

use App\Models\Permission;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Core\Models\SystemSetting;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guardName = 'api';

        $permissions = [
            [
                'name'                => 'system_settings.view',
                'guard_name'          => $guardName,
                'module'              => 'core',
                'module_display_name' => 'إدارة النظام',
                'group_name'          => 'system_settings',
                'group_display_name'  => 'إعدادات النظام العامة',
                'action_name'         => 'view',
                'display_name'        => 'عرض إعدادات النظام',
            ],
            [
                'name'                => 'system_settings.update',
                'guard_name'          => $guardName,
                'module'              => 'core',
                'module_display_name' => 'إدارة النظام',
                'group_name'          => 'system_settings',
                'group_display_name'  => 'إعدادات النظام العامة',
                'action_name'         => 'update',
                'display_name'        => 'تعديل إعدادات النظام',
            ],
        ];

        foreach ($permissions as $permissionData) {
            Permission::updateOrCreate(
                [
                    'name'       => $permissionData['name'],
                    'guard_name' => $permissionData['guard_name'],
                ],
                $permissionData
            );
        }

        $baseCurrencyId = Currency::where('is_base', true)->value('id')
            ?? Currency::query()->value('id');

        SystemSetting::firstOrCreate(
            ['id' => 1],
            [
                'base_currency_id' => $baseCurrencyId,
                'active_modules'   => [
                    'core',
                    'accounting',
                    'inventory',
                    'purchasing',
                    'hr',
                ],
                'company_name'     => 'مؤسستي للحلول الذكية',
            ]
        );
    }
}