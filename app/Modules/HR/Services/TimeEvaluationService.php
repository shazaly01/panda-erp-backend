<?php

declare(strict_types=1);

namespace App\Modules\HR\Services;

use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\AttendanceLog;
use App\Modules\HR\Models\Shift;
use App\Modules\HR\Models\OvertimePolicy;
use Carbon\Carbon;

class TimeEvaluationService
{
    public function __construct(
        protected ScheduleResolutionService $scheduleResolutionService
    ) {}

    /**
     * تقييم سجلات الحضور وتصنيف العمل الإضافي (ساعات وأيام) خلال فترة زمنية محددة.
     */
    public function evaluatePeriod(Employee $employee, string $startDate, string $endDate, OvertimePolicy $policy): array
    {
        // 1. جلب سجلات الحضور الخاصة بالموظف للفترة المالية
        $logs = AttendanceLog::where('employee_id', $employee->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        // 2. تهيئة مصفوفة النتائج المعتمدة في محرك الرواتب
        $evaluated = [
            'OT_REGULAR_HOURS' => 0.0,
            'OT_WEEKEND_HOURS' => 0.0,
            'OT_HOLIDAY_HOURS' => 0.0,
            'OT_REGULAR_DAYS'  => 0,
            'OT_WEEKEND_DAYS'  => 0,
            'OT_HOLIDAY_DAYS'  => 0,
        ];

        // التحقق مما إذا كانت السياسة تشترط اعتماد المشرف للساعات الإضافية
        $isSupervisorSource = ($policy->overtime_source === 'supervisor');

        foreach ($logs as $log) {
            $date = Carbon::parse($log->date);

            // استدعاء العقل المدبر لتحديد التوصيف التشغيلي لليوم
            $resolution = $this->scheduleResolutionService->resolveForDate($employee, $date);

            // التحقق من الحضور الفعلي للموظف
            $hasAttended = ($log->status !== 'absent') && ($log->check_in !== null || $log->overtime_minutes > 0);

            if (!$hasAttended) {
                continue;
            }

            // تحديد عدد ساعات الوردية القياسية للموظف لهذا اليوم
            $standardShiftHours = $this->getStandardShiftHours($resolution['shift'], $policy);

            // حساب إجمالي ساعات التواجد الفعلي
            $workedHours = $this->calculateWorkedHours($log, $standardShiftHours);

            // -------------------------------------------------------------
            // الحالة 1: الاستثناءات العامة (طوارئ الحرب أو العطل الرسمية)
            // -------------------------------------------------------------
            if ($resolution['exception'] !== null || $resolution['type'] === 'exception') {
                // تتبع أيام الحضور الميداني في الطوارئ
                $evaluated['OT_HOLIDAY_DAYS'] += 1;

                // احتساب كامل ساعات العمل المنفذة في يوم الطوارئ كساعات عطلة وطوارئ
                $evaluated['OT_HOLIDAY_HOURS'] += round($workedHours, 2);
                continue;
            }

            // -------------------------------------------------------------
            // الحالة 2: أيام الراحة الأسبوعية (Off Days / Weekend)
            // -------------------------------------------------------------
            if ($resolution['is_off_day']) {
                $evaluated['OT_WEEKEND_DAYS'] += 1;
                // احتساب كامل ساعات العمل المنفذة في يوم الراحة الأسبوعية
                $evaluated['OT_WEEKEND_HOURS'] += round($workedHours, 2);
                continue;
            }

            // -------------------------------------------------------------
            // الحالة 3: أيام العمل المعتادة (Regular Working Days)
            // -------------------------------------------------------------
            if ($log->overtime_minutes > 0) {
                $otHours = $log->overtime_minutes / 60;

                $threshold = $policy->hours_to_day_threshold ?? 0;
                if ($policy->is_daily_basis && $threshold > 0 && $otHours >= $threshold) {
                    $evaluated['OT_REGULAR_DAYS'] += 1;
                } else {
                    $evaluated['OT_REGULAR_HOURS'] += round($otHours, 2);
                }
            } elseif (!$isSupervisorSource && $workedHours > $standardShiftHours) {
                // احتساب الساعات المنفذة بعد انتهاء الوردية المعتادة
                $otHours = $workedHours - $standardShiftHours;
                $evaluated['OT_REGULAR_HOURS'] += round($otHours, 2);
            }
        }

        return $evaluated;
    }

    /**
     * احتساب ساعات الوردية المعتمدة (مع دعم الورديات الليلية)
     */
    protected function getStandardShiftHours(?Shift $shift, OvertimePolicy $policy): float
    {
        if ($shift && $shift->start_time && $shift->end_time) {
            $start = Carbon::parse('2026-01-01 ' . $shift->start_time);
            $end = Carbon::parse('2026-01-01 ' . $shift->end_time);

            if ($end->lessThan($start)) {
                $end->addDay();
            }

            return (float) ($start->diffInMinutes($end) / 60);
        }

        return (float) ($policy->working_hours_per_day > 0 ? $policy->working_hours_per_day : 8.0);
    }

    /**
     * احتساب ساعات التواجد الفعلي من واقع البصمة أو الدقائق الإضافية
     */
    protected function calculateWorkedHours(AttendanceLog $log, float $standardShiftHours): float
    {
        if ($log->check_in && $log->check_out) {
            $dateStr = Carbon::parse($log->date)->toDateString();
            $in = Carbon::parse($dateStr . ' ' . $log->check_in);
            $out = Carbon::parse($dateStr . ' ' . $log->check_out);

            if ($out->lessThan($in)) {
                $out->addDay();
            }

            return (float) ($in->diffInMinutes($out) / 60);
        }

        if ($log->overtime_minutes > 0) {
            return (float) ($log->overtime_minutes / 60);
        }

        return $standardShiftHours;
    }
}