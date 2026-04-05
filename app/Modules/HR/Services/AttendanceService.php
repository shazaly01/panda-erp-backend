<?php

declare(strict_types=1);

namespace App\Modules\HR\Services;

use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\AttendanceLog;
use App\Modules\HR\Models\EmployeeShift;
use Carbon\Carbon;
use Exception;

class AttendanceService
{
    /**
     * تسجيل وتحليل حضور الموظف ليوم معين
     */
    public function processDailyAttendance(Employee $employee, string $date, ?string $checkInTime, ?string $checkOutTime): AttendanceLog
    {
        // 1. جلب وردية الموظف الفعالة في هذا التاريخ
        $employeeShift = EmployeeShift::with('shift')
            ->where('employee_id', $employee->id)
            ->where('start_date', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('end_date')->orWhere('end_date', '>=', $date);
            })
            ->first();

        if (!$employeeShift || !$employeeShift->shift) {
            throw new Exception("لا توجد وردية مسجلة للموظف في هذا التاريخ.");
        }

        $shift = $employeeShift->shift;
        $status = 'present';
        $delayMinutes = 0;
        $earlyLeaveMinutes = 0;
        $overtimeMinutes = 0;

        // 2. حساب التأخير (إذا وجد وقت حضور)
        if ($checkInTime) {
            $actualCheckIn = Carbon::parse($date . ' ' . $checkInTime);
            $shiftStart = Carbon::parse($date . ' ' . $shift->start_time);

            // إضافة فترة السماح
            $allowedStartTime = $shiftStart->copy()->addMinutes($shift->grace_period_minutes);

            if ($actualCheckIn->greaterThan($allowedStartTime)) {
                $status = 'late';
                // نحسب التأخير من وقت الوردية الأصلي (وليس من نهاية فترة السماح) كما هو متبع محاسبياً
                $delayMinutes = $actualCheckIn->diffInMinutes($shiftStart);
            }
        } else {
            $status = 'absent';
        }

        // 3. حساب الانصراف المبكر أو العمل الإضافي (إذا وجد وقت انصراف)
        if ($checkOutTime) {
            $actualCheckOut = Carbon::parse($date . ' ' . $checkOutTime);
            $shiftEnd = Carbon::parse($date . ' ' . $shift->end_time);

            if ($actualCheckOut->lessThan($shiftEnd)) {
                $earlyLeaveMinutes = $shiftEnd->diffInMinutes($actualCheckOut);
            } elseif ($actualCheckOut->greaterThan($shiftEnd)) {
                $overtimeMinutes = $actualCheckOut->diffInMinutes($shiftEnd);
            }
        }

        // 4. حفظ أو تحديث السجل في قاعدة البيانات
        return AttendanceLog::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => $date],
            [
                'shift_id' => $shift->id,
                'check_in' => $checkInTime,
                'check_out' => $checkOutTime,
                'delay_minutes' => $delayMinutes,
                'early_leave_minutes' => $earlyLeaveMinutes,
                'overtime_minutes' => $overtimeMinutes,
                'status' => $status,
            ]
        );
    }
}
