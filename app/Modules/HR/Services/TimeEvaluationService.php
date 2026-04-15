<?php

declare(strict_types=1);

namespace App\Modules\HR\Services;

use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\AttendanceLog;
use App\Modules\HR\Models\EmployeeShift;
use App\Modules\HR\Models\PublicHoliday;
use App\Modules\HR\Models\OvertimePolicy;
use Carbon\Carbon;

class TimeEvaluationService
{
    /**
     * تقييم سجلات الحضور وتصنيف العمل الإضافي (ساعات/أيام) خلال فترة زمنية محددة.
     */
    public function evaluatePeriod(Employee $employee, string $startDate, string $endDate, OvertimePolicy $policy): array
    {
        // 1. جلب سجلات الحضور للفترة المطلوبة
        $logs = AttendanceLog::where('employee_id', $employee->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        // 2. جلب العطلات الرسمية التي تتقاطع مع هذه الفترة
        $publicHolidays = PublicHoliday::where('is_paid', true)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                      ->orWhereBetween('end_date', [$startDate, $endDate]);
            })->get();

        // 3. جلب ورديات الموظف الفعالة في هذه الفترة لمعرفة أيام الراحة الأسبوعية
        $employeeShifts = EmployeeShift::where('employee_id', $employee->id)
            ->where('start_date', '<=', $endDate)
            ->where(function ($query) use ($startDate) {
                $query->whereNull('end_date')->orWhere('end_date', '>=', $startDate);
            })->get();

        // تهيئة المتغيرات التي ستُحقن لاحقاً في محرك الرواتب
        $evaluated = [
            'OT_REGULAR_HOURS' => 0,
            'OT_WEEKEND_HOURS' => 0,
            'OT_HOLIDAY_HOURS' => 0,
            'OT_REGULAR_DAYS'  => 0,
            'OT_WEEKEND_DAYS'  => 0,
            'OT_HOLIDAY_DAYS'  => 0,
        ];

        foreach ($logs as $log) {
            // تجاهل الأيام التي لا يوجد بها عمل إضافي
            if ($log->overtime_minutes <= 0) {
                continue;
            }

            $date = Carbon::parse($log->date);
            $dayOfWeek = strtolower($date->englishDayOfWeek); // مثال: 'friday'
            $otHours = $log->overtime_minutes / 60;

            // أ. فحص هل اليوم يقع ضمن عطلة رسمية (Public Holiday)
            $isHoliday = $publicHolidays->contains(function ($holiday) use ($date) {
                return $date->between(Carbon::parse($holiday->start_date), Carbon::parse($holiday->end_date));
            });

            // ب. فحص هل اليوم هو يوم راحة أسبوعية (Weekend) بناءً على وردية الموظف الحالية
            $isWeekend = false;
            $shift = $employeeShifts->first(function ($s) use ($date) {
                $start = Carbon::parse($s->start_date);
                $end = $s->end_date ? Carbon::parse($s->end_date) : Carbon::parse('2099-12-31');
                return $date->between($start, $end);
            });

            if ($shift && is_array($shift->weekend_days) && in_array($dayOfWeek, $shift->weekend_days)) {
                $isWeekend = true;
            }

            // ج. تصنيف الأوفرتايم (ساعات أو أيام) بناءً على السياسة المربوطة بالعقد
            if ($policy->is_daily_basis && $otHours >= $policy->hours_to_day_threshold) {
                // الحساب بنظام "اليوم الكامل"
                if ($isHoliday) {
                    $evaluated['OT_HOLIDAY_DAYS'] += 1;
                } elseif ($isWeekend) {
                    $evaluated['OT_WEEKEND_DAYS'] += 1;
                } else {
                    $evaluated['OT_REGULAR_DAYS'] += 1;
                }
            } else {
                // الحساب بنظام "الساعات"
                if ($isHoliday) {
                    $evaluated['OT_HOLIDAY_HOURS'] += $otHours;
                } elseif ($isWeekend) {
                    $evaluated['OT_WEEKEND_HOURS'] += $otHours;
                } else {
                    $evaluated['OT_REGULAR_HOURS'] += $otHours;
                }
            }
        }

        return $evaluated;
    }
}
