<?php

declare(strict_types=1);

namespace App\Modules\HR\Services;

use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\CalendarException;
use App\Modules\HR\Models\ShiftOverride;
use App\Modules\HR\Models\LeaveRequest;
use Carbon\Carbon;

class ScheduleResolutionService
{
    /**
     * تحديد حالة دوام الموظف في يوم محدد (تجاوز فردي، إجازة، عطلة، طوارئ، أو وردية معتادة)
     *
     * @param Employee $employee
     * @param Carbon $date
     * @return array
     */
    public function resolveForDate(Employee $employee, Carbon $date): array
    {
        $dateString = $date->toDateString();

        // =========================================================
        // الطبقة 1: التجاوزات الفردية (Individual Overrides) - الأولوية القصوى
        // =========================================================
        $shiftOverride = ShiftOverride::where('employee_id', $employee->id)
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
        $leaveRequest = LeaveRequest::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where('start_date', '<=', $dateString)
            ->where('end_date', '>=', $dateString)
            ->first();

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
        // فحص وجود استثناء عام (Global Exception - مثل طوارئ الحرب)
        // =========================================================
        $exception = CalendarException::where('start_date', '<=', $dateString)
            ->where(function ($query) use ($dateString) {
                $query->whereNull('end_date')
                      ->orWhere('end_date', '>=', $dateString);
            })->first();

        // =========================================================
        // الطبقة 4: القاعدة الأساسية (Base Schedule & Modulo Magic)
        // =========================================================
        $contract = $employee->currentContract;

        if (!$contract || !$contract->working_schedule_id || !$contract->schedule_start_date) {
            return $this->buildResponse(
                type: $exception ? 'exception' : 'no_schedule',
                isOffDay: true,
                treatAsOvertime: $exception ? $exception->treat_as_overtime_if_worked : false,
                shift: null,
                exception: $exception
            );
        }

        $schedule = $contract->workingSchedule;
        $startDate = Carbon::parse($contract->schedule_start_date);

        if ($date->lt($startDate)) {
            return $this->buildResponse(
                type: $exception ? 'exception' : 'before_schedule',
                isOffDay: true,
                treatAsOvertime: $exception ? $exception->treat_as_overtime_if_worked : false,
                shift: null,
                exception: $exception
            );
        }

        // حساب الفارق بالأيام وموقع اليوم داخل دورة الورديات
        $diffInDays = $startDate->diffInDays($date);
        $dayNumberInCycle = ($diffInDays % $schedule->cycle_days) + 1;

        $line = $schedule->lines()->where('day_number', $dayNumberInCycle)->with('shift')->first();

        $baseShift = $line?->shift;
        $isOffDay = !$line || !$line->shift_id;

        // =========================================================
        // الدمج النهائي: إرجاع الوردية الأصلية مع تطبيق شروط الاستثناء (إن وجد)
        // =========================================================
        if ($exception) {
            return $this->buildResponse(
                type: 'exception',
                isOffDay: $isOffDay,
                treatAsOvertime: $exception->treat_as_overtime_if_worked,
                shift: $baseShift, // 🌟 نُرجع وردية الموظف الفعلية (12 ساعة أو 8 ساعات)
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

    /**
     * بناء المصفوفة الموحدة للرد
     */
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
