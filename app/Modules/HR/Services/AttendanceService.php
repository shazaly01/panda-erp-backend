<?php

declare(strict_types=1);

namespace App\Modules\HR\Services;

use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\AttendanceLog;
use Carbon\Carbon;
use Exception;

class AttendanceService
{
    private ScheduleResolutionService $scheduleResolutionService;

    public function __construct(ScheduleResolutionService $scheduleResolutionService)
    {
        $this->scheduleResolutionService = $scheduleResolutionService;
    }

    /**
     * تسجيل وتحليل حضور الموظف ليوم معين بناءً على محرك الجدولة وسياسة العمل الإضافي
     */
    public function processDailyAttendance(
        Employee $employee,
        string $date,
        ?string $checkInTime,
        ?string $checkOutTime,
        ?int $manualOvertimeMinutes = null
    ): AttendanceLog {
        $targetDate = Carbon::parse($date);

        // 1. استخدام العقل المدبر لمعرفة حالة اليوم
        $resolution = $this->scheduleResolutionService->resolveForDate($employee, $targetDate);

        if ($resolution['type'] === 'no_schedule' || $resolution['type'] === 'before_schedule') {
            throw new Exception("الموظف ليس لديه جدول عمل نشط في هذا التاريخ.");
        }

        $shift = $resolution['shift']; // قد يكون null في أيام الراحة أو الطوارئ
        $exception = $resolution['exception'];

        // جلب سياسة العمل الإضافي لتحديد مصدر الحساب (بصمة تلقائية أم اعتماد مشرف)
        $contract = $employee->currentContract ?? $employee->activeContract;
        $overtimePolicy = $contract?->overtimePolicy;
        $isSupervisorSource = ($overtimePolicy?->overtime_source === 'supervisor');

        // جلب السجل الحالي إن وجد للحفاظ على القيمة السابقة عند التحديث بدون إدخال جديد
        $existingLog = AttendanceLog::where('employee_id', $employee->id)
            ->where('date', $date)
            ->first();

        $status = 'present';
        $delayMinutes = 0;
        $earlyLeaveMinutes = 0;
        $overtimeMinutes = 0;

        // --- المعالجة إذا كان اليوم استثناء (طوارئ) أو يوم راحة (عطلة/ويكند) ---
        if ($resolution['treat_as_overtime'] || $resolution['is_off_day']) {
            if ($checkInTime && $checkOutTime) {
                $in = Carbon::parse($date . ' ' . $checkInTime);
                $out = Carbon::parse($date . ' ' . $checkOutTime);

                if ($out->lessThan($in)) {
                    $out->addDay(); // الدوام امتد لليوم التالي
                }

                if ($isSupervisorSource) {
                    $overtimeMinutes = $manualOvertimeMinutes ?? ($existingLog?->overtime_minutes ?? 0);
                } else {
                    $overtimeMinutes = $manualOvertimeMinutes ?? (int) $in->diffInMinutes($out);
                }

                $status = 'present';
            } elseif ($checkInTime && !$checkOutTime) {
                $status = 'present';
                $overtimeMinutes = $manualOvertimeMinutes ?? ($existingLog?->overtime_minutes ?? 0);
            } elseif (!$checkInTime && !$checkOutTime) {
                if ($resolution['type'] === 'leave_day') {
                    $status = 'on_leave';
                } else {
                    $status = $resolution['is_off_day'] ? 'off_day' : 'exception_day';
                }
                $overtimeMinutes = $manualOvertimeMinutes ?? ($existingLog?->overtime_minutes ?? 0);
            }
        }
        // --- المعالجة إذا كان يوم عمل عادي بوردية محددة ---
        elseif ($shift) {
            $shiftStart = Carbon::parse($date . ' ' . $shift->start_time);
            $shiftEnd = Carbon::parse($date . ' ' . $shift->end_time);

            $isNightShift = $shiftEnd->lessThan($shiftStart);
            if ($isNightShift) {
                $shiftEnd->addDay();
            }

            // حساب التأخير (إذا وجد وقت حضور)
            if ($checkInTime) {
                $actualCheckIn = Carbon::parse($date . ' ' . $checkInTime);

                if ($isNightShift && $actualCheckIn->format('H:i:s') < $shiftStart->format('H:i:s')) {
                    $actualCheckIn->addDay();
                }

                $allowedStartTime = $shiftStart->copy()->addMinutes($shift->grace_period_minutes);

                if ($actualCheckIn->greaterThan($allowedStartTime)) {
                    $status = 'late';
                    $delayMinutes = (int) $actualCheckIn->diffInMinutes($shiftStart);
                }
            } else {
                $status = 'absent';
            }

            // حساب الانصراف المبكر أو العمل الإضافي (إذا وجد وقت انصراف)
            if ($checkOutTime) {
                $actualCheckOut = Carbon::parse($date . ' ' . $checkOutTime);

                if ($actualCheckOut->lessThan($shiftStart)) {
                    $actualCheckOut->addDay();
                }

                if ($actualCheckOut->lessThan($shiftEnd)) {
                    $earlyLeaveMinutes = (int) $shiftEnd->diffInMinutes($actualCheckOut);
                } elseif ($actualCheckOut->greaterThan($shiftEnd)) {
                    if (!$isSupervisorSource) {
                        $overtimeMinutes = (int) $actualCheckOut->diffInMinutes($shiftEnd);
                    }
                }
            }

            // إذا كانت السياسة باعتماد المشرف أو تم تمرير دقائق صريحة يدوياً
            if ($isSupervisorSource) {
                $overtimeMinutes = $manualOvertimeMinutes ?? ($existingLog?->overtime_minutes ?? 0);
            } elseif ($manualOvertimeMinutes !== null) {
                $overtimeMinutes = $manualOvertimeMinutes;
            }
        }

        // 2. حفظ أو تحديث السجل في قاعدة البيانات
        return AttendanceLog::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => $date],
            [
                'shift_id'              => $shift ? $shift->id : null,
                'calendar_exception_id' => $exception ? $exception->id : null,
                'check_in'              => $checkInTime,
                'check_out'             => $checkOutTime,
                'delay_minutes'         => $delayMinutes,
                'early_leave_minutes'   => $earlyLeaveMinutes,
                'overtime_minutes'      => $overtimeMinutes,
                'status'                => $status,
            ]
        );
    }

    /**
     * معالجة البصمات التلقائية (مثل الباركود) بناءً على سياسة الحضور
     */
    public function processAutoPunch(Employee $employee, Carbon $punchTime): array
    {
        $physicalDate = $punchTime->toDateString();
        $logicalDate = $physicalDate;

        // ==========================================
        // 1. معالجة الوردية الليلية
        // ==========================================
        $yesterday = $punchTime->copy()->subDay();
        $yesterdayResolution = $this->scheduleResolutionService->resolveForDate($employee, $yesterday);

        if ($yesterdayResolution['shift']) {
            $yShift = $yesterdayResolution['shift'];

            if (Carbon::parse($yShift->end_time)->lessThan(Carbon::parse($yShift->start_time))) {
                $yShiftEnd = Carbon::parse($yesterday->toDateString() . ' ' . $yShift->end_time)->addDay();
                $maxCheckOutTime = $yShiftEnd->copy()->addHours(4);

                if ($punchTime->lessThanOrEqualTo($maxCheckOutTime)) {
                    $logicalDate = $yesterday->toDateString();
                }
            }
        }

        $date = $logicalDate;

        // ==========================================
        // 2. جلب حالة الموظف بالتاريخ المنطقي الصحيح
        // ==========================================
        $targetDate = Carbon::parse($date);
        $resolution = $this->scheduleResolutionService->resolveForDate($employee, $targetDate);

        if (!$resolution['shift']) {
            return $this->handleOffDayPunch($employee, $date, $punchTime, $resolution);
        }

        $shift = $resolution['shift'];

        // ==========================================
        // 3. جلب سجل الحضور إن وجد
        // ==========================================
        $todayLog = AttendanceLog::where('employee_id', $employee->id)
            ->where('date', $date)
            ->first();

        $checkInTime = $todayLog ? $todayLog->check_in : null;
        $checkOutTime = $todayLog ? $todayLog->check_out : null;

        // ==========================================
        // 4. تحديد نوع البصمة
        // ==========================================
        $attendanceMode = $employee->currentContract?->attendance_mode
            ?? config('hr.attendance_mode', 'strict');

        if ($attendanceMode === 'auto_shift_pair') {
            if ($checkInTime) {
                $actionData = [
                    'status'  => 'warning',
                    'action'  => 'ignored',
                    'message' => 'تم تسجيل حضورك وانصرافك لهذا اليوم مسبقاً.'
                ];
            } else {
                $shiftStart = Carbon::parse($date . ' ' . $shift->start_time);
                $shiftEnd   = Carbon::parse($date . ' ' . $shift->end_time);

                if ($shiftEnd->lessThan($shiftStart)) {
                    $shiftEnd->addDay();
                }

                $shiftDurationMinutes = $shiftStart->diffInMinutes($shiftEnd);

                $checkInTime  = $shift->start_time;
                $checkOutTime = $shift->end_time;

                $actionData = [
                    'status'  => 'success',
                    'action'  => 'check_in',
                    'message' => 'أهلاً بك، تم تسجيل الحضور والانصراف تلقائياً بناءً على ورديتك (' . round($shiftDurationMinutes / 60, 1) . ' ساعة).'
                ];
            }
        } elseif ($attendanceMode === 'single_punch') {
            if ($checkInTime) {
                $actionData = ['status' => 'warning', 'action' => 'ignored', 'message' => 'تم تسجيل حضورك مسبقاً (نظام البصمة الواحدة).'];
            } else {
                $actionData = ['status' => 'success', 'action' => 'check_in', 'time' => $punchTime->toTimeString(), 'message' => 'أهلاً بك، تم تسجيل الحضور.'];
            }
        } else {
            $shiftStart = Carbon::parse($date . ' ' . $shift->start_time);
            $shiftEnd = Carbon::parse($date . ' ' . $shift->end_time);

            if ($shiftEnd->lessThan($shiftStart)) {
                $shiftEnd->addDay();
            }

            $shiftDuration = $shiftStart->diffInMinutes($shiftEnd);
            $midPoint = $shiftStart->copy()->addMinutes($shiftDuration / 2);

            $isCheckIn = $punchTime->lessThan($midPoint);
            $actionData = $this->determinePunchAction($isCheckIn, $punchTime, $checkInTime, $checkOutTime);
        }

        // ==========================================
        // 5. التوجيه النهائي للبيانات
        // ==========================================
        if ($actionData['status'] === 'warning') {
            return $actionData;
        }

        if ($attendanceMode !== 'auto_shift_pair') {
            if ($actionData['action'] === 'check_in') {
                $checkInTime = $actionData['time'];
            } else {
                $checkOutTime = $actionData['time'];
            }
        }

        // ==========================================
        // 6. الحفظ النهائي في قاعدة البيانات
        // ==========================================
        $this->processDailyAttendance(
            $employee,
            $date,
            $checkInTime,
            $checkOutTime
        );

        // ==========================================
        // 7. الصرف الآلي لكود الإنترنت
        // ==========================================
        $voucherCode = null;
        if ($actionData['action'] === 'check_in') {
            try {
                $logId = AttendanceLog::where('employee_id', $employee->id)->where('date', $date)->value('id');

                if ($logId) {
                    $voucher = app(\App\Modules\HR\Services\InternetVoucherService::class)
                        ->assignAutoVoucher($employee->id, $logId);

                    $voucherCode = $voucher->code;
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('فشل صرف كود إنترنت آلي للموظف ' . $employee->id . ': ' . $e->getMessage());
            }
        }

        return [
            'status'  => 'success',
            'action'  => $actionData['action'],
            'message' => $actionData['message'],
            'voucher' => $voucherCode
        ];
    }

    /**
     * تحديد نوع البصمة مع الاعتماد على العزل الزمني لسد ثغرة منتصف الليل
     */
    private function determinePunchAction(bool $isCheckIn, Carbon $punchTime, ?string $checkInTime, ?string $checkOutTime): array
    {
        if ($isCheckIn) {
            if ($checkInTime && $this->isDuplicatePunch($punchTime, $checkInTime)) {
                return ['status' => 'warning', 'action' => 'ignored', 'message' => 'تم تسجيل حضورك بالفعل قبل قليل.'];
            }
            return ['status' => 'success', 'action' => 'check_in', 'time' => $punchTime->toTimeString(), 'message' => 'أهلاً بك، تم تسجيل الحضور بنجاح.'];
        } else {
            if ($checkOutTime && $this->isDuplicatePunch($punchTime, $checkOutTime)) {
                return ['status' => 'warning', 'action' => 'ignored', 'message' => 'تم تسجيل انصرافك بالفعل قبل قليل.'];
            }
            return ['status' => 'success', 'action' => 'check_out', 'time' => $punchTime->toTimeString(), 'message' => 'رافقتك السلامة، تم تسجيل الانصراف.'];
        }
    }

    /**
     * كشف البصمات المكررة
     */
    private function isDuplicatePunch(Carbon $punchTime, string $storedTimeStr): bool
    {
        $existingTime = Carbon::parse($punchTime->toDateString() . ' ' . $storedTimeStr);

        if ($existingTime->diffInMinutes($punchTime) > 720) {
            if ($existingTime->greaterThan($punchTime)) {
                $existingTime->subDay();
            } else {
                $existingTime->addDay();
            }
        }

        return $punchTime->diffInMinutes($existingTime) < 5;
    }

    /**
     * معالجة استثنائية: إذا جاء الموظف وبصم في يوم راحة أو طوارئ
     */
    private function handleOffDayPunch(Employee $employee, string $date, Carbon $punchTime, array $resolution): array
    {
        $todayLog = AttendanceLog::where('employee_id', $employee->id)->where('date', $date)->first();
        $checkInTime = $todayLog ? $todayLog->check_in : null;
        $checkOutTime = $todayLog ? $todayLog->check_out : null;

        $action = 'check_in';
        $message = 'تم تسجيل حضورك الإضافي.';

        if ($checkInTime) {
            $existingCheckIn = Carbon::parse($date . ' ' . $checkInTime);
            if ($punchTime->diffInMinutes($existingCheckIn) > 60) {
                $checkOutTime = $punchTime->toTimeString();
                $action = 'check_out';
                $message = 'تم تسجيل انصرافك الإضافي.';
            } else {
                return ['status' => 'warning', 'action' => 'ignored', 'message' => 'تم تسجيل بصمتك قبل قليل.'];
            }
        } else {
            $checkInTime = $punchTime->toTimeString();
        }

        $this->processDailyAttendance($employee, $date, $checkInTime, $checkOutTime);

        return [
            'status'  => 'success',
            'action'  => $action,
            'message' => $message
        ];
    }
}