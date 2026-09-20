<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Core\Http\Requests\SystemSetting\UpdateSystemSettingRequest;
use App\Modules\Core\Http\Resources\SystemSettingResource;
use App\Modules\Core\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SystemSettingController extends Controller
{
    /**
     * جلب إعدادات النظام الحالية
     */
    public function show(): SystemSettingResource
    {
        $this->authorize('viewAny', SystemSetting::class);

        $settings = Cache::rememberForever('system_settings', function () {
            return SystemSetting::with('baseCurrency')->firstOrCreate(
                ['id' => 1],
                [
                    'active_modules' => ['core', 'accounting', 'inventory', 'purchasing', 'hr'],
                    'company_name'   => 'مؤسستي للحلول الذكية',
                ]
            );
        });

        return new SystemSettingResource($settings);
    }

    /**
     * تحديث إعدادات النظام ومزامنة العملة الأساسية والموديولات
     */
    public function update(UpdateSystemSettingRequest $request): SystemSettingResource
    {
        $settings = SystemSetting::firstOrCreate(
            ['id' => 1],
            [
                'active_modules' => ['core', 'accounting', 'inventory', 'purchasing', 'hr'],
                'company_name'   => 'مؤسستي للحلول الذكية',
            ]
        );

        $this->authorize('update', $settings);

        $data = $request->validated();

        DB::transaction(function () use ($settings, $data) {
            // مزامنة العملة الأساسية في جدول currencies إن تم إرسالها
            if (!empty($data['base_currency_id'])) {
                Currency::where('is_base', true)->update(['is_base' => false]);
                Currency::where('id', $data['base_currency_id'])->update(['is_base' => true]);
            }

            $settings->update([
                'base_currency_id' => $data['base_currency_id'] ?? $settings->base_currency_id,
                'active_modules'   => $data['active_modules'],
                'company_name'     => $data['company_name'] ?? $settings->company_name,
            ]);

            // إفراغ الكاش لضمان سحب القيم المحدثة فوراً
            Cache::forget('system_settings');
        });

        $settings->load('baseCurrency');

        return new SystemSettingResource($settings);
    }
}