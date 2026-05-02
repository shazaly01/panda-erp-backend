<?php

declare(strict_types=1);

namespace App\Modules\HR\Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\HR\Models\Shift;

class ShiftSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. وردية الإدارة
        Shift::firstOrCreate(
            ['name' => 'وردية الإدارة (صباحي)'],
            [
                'start_time' => '07:00:00',
                'end_time' => '15:00:00',
                'grace_period_minutes' => 15,
                'is_active' => true,
            ]
        );

        // 2. وردية التشغيل الصباحية
        Shift::firstOrCreate(
            ['name' => 'وردية التشغيل (صباحي)'],
            [
                'start_time' => '07:00:00',
                'end_time' => '15:00:00',
                'grace_period_minutes' => 15,
                'is_active' => true,
            ]
        );

        // 3. وردية التشغيل المسائية
        Shift::firstOrCreate(
            ['name' => 'وردية التشغيل (مسائي)'],
            [
                'start_time' => '15:00:00',
                'end_time' => '23:00:00',
                'grace_period_minutes' => 15,
                'is_active' => true,
            ]
        );
    }
}
