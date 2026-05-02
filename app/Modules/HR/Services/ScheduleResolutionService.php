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
            // إذا كانت الوردية الجديدة Null، فهذا يعني أن المدير أعفاه من الدوام في هذا اليوم (تحويله ليوم راحة)
            if (!$shiftOverride->new_shift_id) {
                return $this->buildResponse(
                    type: 'override_off_day',
                    isOffDay: true,
                    treatAsOvertime: false
                );
            }

            // إذا تم تبديل ورديته بوردية أخرى في هذا اليوم
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
                isOffDay: true, // يعتبر يوم إجازة (الغياب فيه مبرر ومدفوع حسب نوع الإجازة)
                treatAsOvertime: false, // العمل في الإجازة يعود لسياسة الشركة، لكن افتراضياً لا يحسب كإضافي إلا بإذن خاص
                shift: null,
                exception: null,
                leave: $leaveRequest
            );
        }

        // =========================================================
        // الطبقة 3: التجاوزات العامة (Global Exceptions - طوارئ وعطلات)
        // =========================================================
        $exception = CalendarException::where('start_date', '<=', $dateString)
            ->where(function ($query) use ($dateString) {
                // يدعم الحالات المستمرة (مثل طوارئ الحرب) حيث يكون end_date فارغاً
                $query->whereNull('end_date')
                      ->orWhere('end_date', '>=', $dateString);
            })->first();

        if ($exception) {
            return $this->buildResponse(
                type: 'exception',
                isOffDay: false,
                treatAsOvertime: $exception->treat_as_overtime_if_worked,
                shift: null,
                exception: $exception
            );
        }

        // =========================================================
        // الطبقة 4: القاعدة الأساسية (Base Schedule & Modulo Magic)
        // =========================================================
        $contract = $employee->currentContract;

        if (!$contract || !$contract->working_schedule_id || !$contract->schedule_start_date) {
            // الموظف ليس لديه عقد، أو غير مربوط بجدول، أو لا يوجد تاريخ بداية للدورة
            return $this->buildResponse(
                type: 'no_schedule',
                isOffDay: true,
                treatAsOvertime: false
            );
        }

        $schedule = $contract->workingSchedule;
        $startDate = Carbon::parse($contract->schedule_start_date);

        // إذا كان التاريخ المطلوب قبل بداية عقد الموظف أو دورته
        if ($date->lt($startDate)) {
            return $this->buildResponse(
                type: 'before_schedule',
                isOffDay: true,
                treatAsOvertime: false
            );
        }

        // حساب الفارق بالأيام بين تاريخ البداية والتاريخ المطلوب
        $diffInDays = $startDate->diffInDays($date);

        // العملية الرياضية لمعرفة موقع اليوم داخل دورة الورديات
        $dayNumberInCycle = ($diffInDays % $schedule->cycle_days) + 1;

        // جلب تفاصيل هذا اليوم تحديداً من القالب
        $line = $schedule->lines()->where('day_number', $dayNumberInCycle)->with('shift')->first();

        // إذا لم يوجد خط لهذا اليوم، أو الـ shift_id فارغ (Null)، فهذا يوم راحة (Off Day) الافتراضي
        if (!$line || !$line->shift_id) {
            return $this->buildResponse(
                type: 'off_day',
                isOffDay: true,
                treatAsOvertime: true // العمل في يوم الراحة يُحسب كإضافي
            );
        }

        // إذا كان يوماً عادياً، نرجع الوردية المطلوبة منه
        return $this->buildResponse(
            type: 'working_day',
            isOffDay: false,
            treatAsOvertime: false,
            shift: $line->shift
        );
    }

    /**
     * بناء المصفوفة الموحدة للرد
     */
    private function buildResponse(string $type, bool $isOffDay, bool $treatAsOvertime, $shift = null, $exception = null, $leave = null): array
    {
        return [
            'type'              => $type,               // نوع اليوم (عمل، راحة، استثناء، إجازة، تجاوز)
            'is_off_day'        => $isOffDay,           // هل هو يوم راحة/عطلة؟
            'treat_as_overtime' => $treatAsOvertime,    // هل العمل في هذا اليوم يُعتبر إضافي بالكامل؟
            'shift'             => $shift,              // كائن الوردية (Shift Model)
            'exception'         => $exception,          // كائن الاستثناء (CalendarException Model)
            'leave'             => $leave,              // 🌟 كائن الإجازة (LeaveRequest Model) إذا كان اليوم يوم إجازة
        ];
    }
}
