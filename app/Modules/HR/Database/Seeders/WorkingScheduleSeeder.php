<?php

declare(strict_types=1);

namespace App\Modules\HR\Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\HR\Models\Shift;
use App\Modules\HR\Models\WorkingSchedule;
use Carbon\Carbon;

class WorkingScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $managementShift = Shift::where('name', 'وردية الإدارة (صباحي)')->first();
        $morningOpShift = Shift::where('name', 'وردية التشغيل (صباحي)')->first();
        $eveningOpShift = Shift::where('name', 'وردية التشغيل (مسائي)')->first();

        // =========================================================
        // 1. إنشاء قالب الإدارة (دورة 7 أيام)
        // =========================================================
        $managementSchedule = WorkingSchedule::firstOrCreate(
            ['name' => 'جدول الإدارة الأسبوعي'],
            [
                'type' => 'fixed',
                'cycle_days' => 7,
            ]
        );

        if ($managementSchedule->lines()->count() === 0) {
            $managementLines = [];
            for ($day = 1; $day <= 7; $day++) {
                $managementLines[] = [
                    'working_schedule_id' => $managementSchedule->id,
                    'day_number'          => $day,
                    'shift_id'            => ($day <= 5) ? $managementShift->id : null,
                    'created_at'          => Carbon::now(),
                    'updated_at'          => Carbon::now(),
                ];
            }
            $managementSchedule->lines()->insert($managementLines);
        }

        // =========================================================
        // 2. إنشاء قوالب مجموعات التشغيل الأربعة (دورة 28 يوماً لكل منها)
        // =========================================================
        $operationGroups = [
            'A' => 'جدول التشغيل - مجموعة A',
            'B' => 'جدول التشغيل - مجموعة B',
            'C' => 'جدول التشغيل - مجموعة C',
            'D' => 'جدول التشغيل - مجموعة D',
        ];

        foreach ($operationGroups as $groupCode => $scheduleName) {
            $operationSchedule = WorkingSchedule::firstOrCreate(
                ['name' => $scheduleName],
                [
                    'type' => 'rotating',
                    'cycle_days' => 28,
                ]
            );

            if ($operationSchedule->lines()->count() === 0) {
                $operationLines = [];
                for ($day = 1; $day <= 28; $day++) {
                    $shiftId = null; // الافتراضي: راحة

                    // تطبيق نمط كل مجموعة المدمج (Built-in Offset)
                    switch ($groupCode) {
                        case 'A':
                            if ($day >= 1 && $day <= 7) $shiftId = $morningOpShift->id;
                            elseif ($day >= 8 && $day <= 14) $shiftId = $eveningOpShift->id;
                            break;

                        case 'B':
                            if ($day >= 1 && $day <= 7) $shiftId = $eveningOpShift->id;
                            elseif ($day >= 22 && $day <= 28) $shiftId = $morningOpShift->id;
                            break;

                        case 'C':
                            if ($day >= 15 && $day <= 21) $shiftId = $morningOpShift->id;
                            elseif ($day >= 22 && $day <= 28) $shiftId = $eveningOpShift->id;
                            break;

                        case 'D':
                            if ($day >= 8 && $day <= 14) $shiftId = $morningOpShift->id;
                            elseif ($day >= 15 && $day <= 21) $shiftId = $eveningOpShift->id;
                            break;
                    }

                    $operationLines[] = [
                        'working_schedule_id' => $operationSchedule->id,
                        'day_number'          => $day,
                        'shift_id'            => $shiftId,
                        'created_at'          => Carbon::now(),
                        'updated_at'          => Carbon::now(),
                    ];
                }
                $operationSchedule->lines()->insert($operationLines);
            }
        }
    }
}
