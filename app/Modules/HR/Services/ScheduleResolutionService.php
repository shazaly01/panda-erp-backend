<?php

declare(strict_types=1);

namespace App\Modules\HR\Services;

use App\Modules\HR\Models\CalendarException;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\LeaveRequest;
use App\Modules\HR\Models\ShiftOverride;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ScheduleResolutionService
{
    /**
     * مخزن مؤقت في الذاكرة لمنع تكرار الاستعلامات (N+1) أثناء معالجة الفترات المالية
     */
    protected array $preloadedOverrides = [];
    protected array $preloadedLeaves = [];
    protected ?Collection $preloadedExceptions = null;
    protected ?array $preloadedRange = null;

    /**
     * التحميل المسبق لبيانات الجدولة لمجموعة موظفين خلال فترة محددة
     */
    public function preloadForPeriod(array $employeeIds, string $startDate, string $endDate): void
    {
        $this->preloadedRange = [$startDate, $endDate];
        $this->preloadedOverrides = [];
        $this->preloadedLeaves = [];

        $this->preloadedExceptions = CalendarException::where('start_date', '<=', $endDate)
            ->where(function ($query) use ($startDate): void {
                $query->whereNull('end_date')
                      ->orWhere('end_date', '>=', $startDate);
            })
            ->get();

        if (empty($employeeIds)) {
            return;
        }

        $overrides = ShiftOverride::whereIn('employee_id', $employeeIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->with('newShift')
            ->get();

        foreach ($overrides as $override) {
            $dateKey = Carbon::parse($override->date)->toDateString();
            $this->preloadedOverrides[$override->employee_id][$dateKey] = $override;
        }

        $leaves = LeaveRequest::whereIn('employee_id', $employeeIds)
            ->where('status', 'approved')
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->get();

        foreach ($leaves as $leave) {
            $this->preloadedLeaves[$leave->employee_id][] = $leave;
        }
    }

    /**
     * ضمان تحميل بيانات موظف واحد في الذاكرة عند تقييم فترة كاملة له
     */
    public function ensureLoadedForEmployee(int $employeeId, string $startDate, string $endDate): void
    {
        if (
            $this->preloadedRange === [$startDate, $endDate]
            && array_key_exists($employeeId, $this->preloadedOverrides)
        ) {
            return;
        }

        if ($this->preloadedRange !== [$startDate, $endDate]) {
            $this->preloadedRange = [$startDate, $endDate];
            $this->preloadedOverrides = [];
            $this->preloadedLeaves = [];
            $this->preloadedExceptions = CalendarException::where('start_date', '<=', $endDate)
                ->where(function ($query) use ($startDate): void {
                    $query->whereNull('end_date')
                          ->orWhere('end_date', '>=', $startDate);
                })
                ->get();
        }

        $this->preloadedOverrides[$employeeId] = [];
        $overrides = ShiftOverride::where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->with('newShift')
            ->get();

        foreach ($overrides as $override) {
            $dateKey = Carbon::parse($override->date)->toDateString();
            $this->preloadedOverrides[$employeeId][$dateKey] = $override;
        }

        $this->preloadedLeaves[$employeeId] = LeaveRequest::where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->get()
            ->all();
    }

    /**
     * تحديد حالة دوام الموظف في يوم محدد (تجاوز فردي، إجازة، عطلة، طوارئ، أو وردية معتادة)
     */
    public function resolveForDate(Employee $employee, Carbon $date): array
    {
        $dateString = $date->toDateString();
        $isPreloadedForEmployee = $this->isDatePreloaded($employee->id, $dateString);

        // =========================================================
        // الطبقة 1: التجاوزات الفردية (Individual Overrides) - الأولوية القصوى
        // =========================================================
        $shiftOverride = $isPreloadedForEmployee
            ? ($this->preloadedOverrides[$employee->id][$dateString] ?? null)
            : ShiftOverride::where('employee_id', $employee->id)
                ->where('date', $dateString)
                ->with('newShift')
                ->first();

        if ($shiftOverride) {
            if (!$shiftOverride->new_shift_id) {
                return $this->buildResponse(
                    type: 'override_off_day',
                    isOffDay: true,
                    treatAsOvertime: false
                );
            }

            return $this->buildResponse(
                type: 'override_shift',
                isOffDay: false,
                treatAsOvertime: false,
                shift: $shiftOverride->newShift
            );
        }

        // =========================================================
        // الطبقة 2: الإجازات الفردية المعتمدة (Approved Personal Leaves)
        // =========================================================
        $leaveRequest = $this->findLeaveForDate($employee->id, $dateString, $isPreloadedForEmployee);

        if ($leaveRequest) {
            return $this->buildResponse(
                type: 'leave_day',
                isOffDay: true,
                treatAsOvertime: false,
                shift: null,
                exception: null,
                leave: $leaveRequest
            );
        }

        // =========================================================
        // الطبقة 3: الاستثناءات التقويمية العامة (العطلات الرسمية والطوارئ)
        // =========================================================
        $exception = $this->findExceptionForDate($dateString);

        // =========================================================
        // الطبقة 4: القاعدة الأساسية (Base Schedule & Modulo Magic)
        // =========================================================
        $contract = $employee->contractForDate($date) ?? $employee->currentContract;

        if (!$contract || !$contract->working_schedule_id || !$contract->schedule_start_date) {
            return $this->buildResponse(
                type: $exception ? 'exception' : 'no_schedule',
                isOffDay: false,
                treatAsOvertime: $exception ? (bool) $exception->treat_as_overtime_if_worked : false,
                shift: null,
                exception: $exception
            );
        }

        $schedule = $contract->workingSchedule;
        $startDate = Carbon::parse($contract->schedule_start_date)->startOfDay();
        $targetDate = $date->copy()->startOfDay();

        if (!$schedule || (int) $schedule->cycle_days <= 0 || $targetDate->lt($startDate)) {
            return $this->buildResponse(
                type: $exception ? 'exception' : 'before_schedule',
                isOffDay: false,
                treatAsOvertime: $exception ? (bool) $exception->treat_as_overtime_if_worked : false,
                shift: null,
                exception: $exception
            );
        }

        // حساب الفارق بالأيام وموقع اليوم داخل دورة الورديات
        $diffInDays = (int) $startDate->diffInDays($targetDate);
        $dayNumberInCycle = ($diffInDays % (int) $schedule->cycle_days) + 1;

        $line = $schedule->relationLoaded('lines')
            ? $schedule->lines->firstWhere('day_number', $dayNumberInCycle)
            : $schedule->lines()->where('day_number', $dayNumberInCycle)->with('shift')->first();

        $baseShift = $line?->shift;
        $isOffDay = !$line || !$line->shift_id;

        // =========================================================
        // الدمج النهائي: إرجاع الوردية الأصلية مع تطبيق شروط الاستثناء (إن وجد)
        // =========================================================
        if ($exception) {
            return $this->buildResponse(
                type: 'exception',
                isOffDay: $isOffDay,
                treatAsOvertime: (bool) $exception->treat_as_overtime_if_worked,
                shift: $baseShift,
                exception: $exception
            );
        }

        if ($isOffDay) {
            return $this->buildResponse(
                type: 'off_day',
                isOffDay: true,
                treatAsOvertime: true
            );
        }

        return $this->buildResponse(
            type: 'working_day',
            isOffDay: false,
            treatAsOvertime: false,
            shift: $baseShift
        );
    }

    protected function isDatePreloaded(int $employeeId, string $dateString): bool
    {
        if ($this->preloadedRange === null) {
            return false;
        }

        [$start, $end] = $this->preloadedRange;

        return $dateString >= $start
            && $dateString <= $end
            && array_key_exists($employeeId, $this->preloadedOverrides);
    }

    protected function findLeaveForDate(int $employeeId, string $dateString, bool $isPreloaded): ?LeaveRequest
    {
        if ($isPreloaded) {
            foreach ($this->preloadedLeaves[$employeeId] ?? [] as $leave) {
                $leaveStart = Carbon::parse($leave->start_date)->toDateString();
                $leaveEnd = Carbon::parse($leave->end_date)->toDateString();
                if ($leaveStart <= $dateString && $leaveEnd >= $dateString) {
                    return $leave;
                }
            }

            return null;
        }

        return LeaveRequest::where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->where('start_date', '<=', $dateString)
            ->where('end_date', '>=', $dateString)
            ->first();
    }

    protected function findExceptionForDate(string $dateString): ?CalendarException
    {
        if ($this->preloadedExceptions !== null && $this->preloadedRange !== null) {
            [$start, $end] = $this->preloadedRange;
            if ($dateString >= $start && $dateString <= $end) {
                return $this->preloadedExceptions->first(function (CalendarException $exc) use ($dateString): bool {
                    $excStart = Carbon::parse($exc->start_date)->toDateString();
                    $excEnd = $exc->end_date ? Carbon::parse($exc->end_date)->toDateString() : null;

                    return $excStart <= $dateString && ($excEnd === null || $excEnd >= $dateString);
                });
            }
        }

        return CalendarException::where('start_date', '<=', $dateString)
            ->where(function ($query) use ($dateString): void {
                $query->whereNull('end_date')
                      ->orWhere('end_date', '>=', $dateString);
            })
            ->first();
    }

    private function buildResponse(string $type, bool $isOffDay, bool $treatAsOvertime, $shift = null, $exception = null, $leave = null): array
    {
        return [
            'type'              => $type,
            'is_off_day'        => $isOffDay,
            'treat_as_overtime' => $treatAsOvertime,
            'shift'             => $shift,
            'exception'         => $exception,
            'leave'             => $leave,
        ];
    }
}