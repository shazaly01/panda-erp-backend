<?php

declare(strict_types=1);

namespace App\Modules\HR\Services;

use App\Modules\HR\Models\WorkingSchedule;
use Illuminate\Support\Facades\DB;
use Exception;

class WorkingScheduleService
{
    /**
     * إنشاء قالب جدولة جديد مع أيامه
     */
    public function createSchedule(array $data): WorkingSchedule
    {
        return DB::transaction(function () use ($data) {
            // 1. إنشاء رأس القالب
            $schedule = WorkingSchedule::create([
                'name'       => $data['name'],
                'type'       => $data['type'],
                'cycle_days' => $data['cycle_days'],
            ]);

            // 2. إدخال الأيام (السطور) المرتبطة بالقالب
            $linesToInsert = [];
            foreach ($data['lines'] as $line) {
                $linesToInsert[] = [
                    'working_schedule_id' => $schedule->id,
                    'day_number'          => $line['day_number'],
                    'shift_id'            => $line['shift_id'] ?? null,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ];
            }

            // استخدام insert لتقليل عدد الاستعلامات (Performance Optimization)
            $schedule->lines()->insert($linesToInsert);

            // إرجاع القالب مع العلاقات ليتم عرضها في הـ Resource
            return $schedule->load('lines.shift');
        });
    }

    /**
     * تحديث قالب الجدولة وأيامه
     */
    public function updateSchedule(WorkingSchedule $schedule, array $data): WorkingSchedule
    {
        return DB::transaction(function () use ($schedule, $data) {
            // 1. تحديث بيانات رأس القالب
            $schedule->update([
                'name'       => $data['name'],
                'type'       => $data['type'],
                'cycle_days' => $data['cycle_days'],
            ]);

            // 2. حذف الأيام القديمة كلياً (Hard Delete للسطور المرتبطة لتجنب تراكم البيانات)
            $schedule->lines()->forceDelete();

            // 3. إدخال الأيام الجديدة
            $linesToInsert = [];
            foreach ($data['lines'] as $line) {
                $linesToInsert[] = [
                    'working_schedule_id' => $schedule->id,
                    'day_number'          => $line['day_number'],
                    'shift_id'            => $line['shift_id'] ?? null,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ];
            }

            $schedule->lines()->insert($linesToInsert);

            return $schedule->load('lines.shift');
        });
    }

    /**
     * حذف قالب الجدولة
     */
    public function deleteSchedule(WorkingSchedule $schedule): bool
    {
        // السطور المرتبطة (Lines) سيتم حذفها آلياً بفضل cascadeOnDelete في الـ Migration،
        // أو يمكن الاعتماد على الـ SoftDeletes هنا
        return $schedule->delete();
    }
}
