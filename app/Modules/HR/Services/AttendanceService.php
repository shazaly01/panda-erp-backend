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
     * تسجيل وتحليل حضور الموظف ليوم معين بناءً على محرك الجدولة الجديد
     */
    public function processDailyAttendance(Employee $employee, string $date, ?string $checkInTime, ?string $checkOutTime): AttendanceLog
    {
        $targetDate = Carbon::parse($date);

        // 1. استخدام العقل المدبر لمعرفة حالة اليوم
        $resolution = $this->scheduleResolutionService->resolveForDate($employee, $targetDate);

        if ($resolution['type'] === 'no_schedule' || $resolution['type'] === 'before_schedule') {
            throw new Exception("الموظف ليس لديه جدول عمل نشط في هذا التاريخ.");
        }

        $shift = $resolution['shift']; // قد يكون null في أيام الراحة أو الطوارئ
        $exception = $resolution['exception'];

        $status = 'present';
        $delayMinutes = 0;
        $earlyLeaveMinutes = 0;
        $overtimeMinutes = 0;

        // --- المعالجة إذا كان اليوم استثناء (طوارئ) أو يوم راحة (عطلة/ويكند) ---
        // --- المعالجة إذا كان اليوم استثناء (طوارئ) أو يوم راحة (عطلة/ويكند) ---
if ($resolution['treat_as_overtime'] || $resolution['is_off_day']) {
    if ($checkInTime && $checkOutTime) {
        // كل الدوام يعتبر إضافي لأنه يعمل في يوم راحة أو طوارئ
        $in = Carbon::parse($date . ' ' . $checkInTime);
        $out = Carbon::parse($date . ' ' . $checkOutTime);

        if ($out->lessThan($in)) {
            $out->addDay(); // الدوام امتد لليوم التالي
        }

        $overtimeMinutes = $in->diffInMinutes($out);
        $status = 'present'; // 👈 إضافة صريحة لتأكيد حالة الحضور
    } elseif ($checkInTime && !$checkOutTime) {
        $status = 'present'; // 👈 حالة الموظف الذي بصم دخولاً ولم يبصم خروجاً بعد
    } elseif (!$checkInTime && !$checkOutTime) {
        if ($resolution['type'] === 'leave_day') {
            $status = 'on_leave';
        } else {
            $status = $resolution['is_off_day'] ? 'off_day' : 'exception_day';
        }
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
                    $delayMinutes = $actualCheckIn->diffInMinutes($shiftStart);
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
                    $earlyLeaveMinutes = $shiftEnd->diffInMinutes($actualCheckOut);
                } elseif ($actualCheckOut->greaterThan($shiftEnd)) {
                    $overtimeMinutes = $actualCheckOut->diffInMinutes($shiftEnd);
                }
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
     * معالجة البصمات التلقائية (مثل الباركود) بناءً على سياسة الحضور (صارم، بصمة واحدة، أو توليد تلقائي حسب الوردية)
     */
    public function processAutoPunch(Employee $employee, Carbon $punchTime): array
    {
        $physicalDate = $punchTime->toDateString();
        $logicalDate = $physicalDate;

        // ==========================================
        // 1. الذكاء الاصطناعي لمعالجة الوردية الليلية
        // ==========================================
        $yesterday = $punchTime->copy()->subDay();

        // استدعاء المحرك الجديد لمعرفة حالة الأمس
        $yesterdayResolution = $this->scheduleResolutionService->resolveForDate($employee, $yesterday);

        // إذا كان هناك وردية بالأمس
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

        // إذا كان يوم استثناء بالكامل أو يوم راحة، لا يوجد منتصف وردية لحسابه
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
        // 4. تحديد نوع البصمة (التوجيه حسب نظام الشركة)
        // ==========================================
       $attendanceMode = $employee->currentContract?->attendance_mode
    ?? config('hr.attendance_mode', 'strict');

        if ($attendanceMode === 'auto_shift_pair') {
            // 🌟 1. الوضع الجديد: التوليد التلقائي بناءً على ساعات الوردية الحقيقية (12 ساعة، 8 ساعات... إلخ)
            if ($checkInTime) {
                $actionData = [
                    'status'  => 'warning',
                    'action'  => 'ignored',
                    'message' => 'تم تسجيل حضورك وانصرافك لهذا اليوم مسبقاً.'
                ];
            } else {
                // احتساب طول الوردية بالدقائق بناءً على جدول الموظف لليوم
                $shiftStart = Carbon::parse($date . ' ' . $shift->start_time);
                $shiftEnd   = Carbon::parse($date . ' ' . $shift->end_time);

                if ($shiftEnd->lessThan($shiftStart)) {
                    $shiftEnd->addDay();
                }

                $shiftDurationMinutes = $shiftStart->diffInMinutes($shiftEnd);

                // تعيين الدخول والانصراف آلياً بناءً على ساعات الوردية الفعلية
               $checkInTime  = $shift->start_time;
$checkOutTime = $shift->end_time;

                $actionData = [
                    'status'  => 'success',
                    'action'  => 'check_in', // نحددها check_in حتى يتم صرف كود الإنترنت آلياً في الخطوة 7
                    'message' => 'أهلاً بك، تم تسجيل الحضور والانصراف تلقائياً بناءً على ورديتك (' . round($shiftDurationMinutes / 60, 1) . ' ساعة).'
                ];
            }
        } elseif ($attendanceMode === 'single_punch') {
            // 🌟 2. نظام البصمة الواحدة: لا يوجد انصراف، وأي بصمة إضافية تُعتبر مكررة
            if ($checkInTime) {
                $actionData = ['status' => 'warning', 'action' => 'ignored', 'message' => 'تم تسجيل حضورك مسبقاً (نظام البصمة الواحدة).'];
            } else {
                $actionData = ['status' => 'success', 'action' => 'check_in', 'time' => $punchTime->toTimeString(), 'message' => 'أهلاً بك، تم تسجيل الحضور.'];
            }
        } else {
            // 🌟 3. النظام الصارم (Strict): حساب نقطة المنتصف (Midpoint Logic)
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
            return $actionData; // تجاهل البصمة المكررة
        }

        // في غير وضع auto_shift_pair، نأخذ الوقت من actionData['time']
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
        // 7. 🚀 الصرف الآلي لكود الإنترنت (الربط مع نظام IT)
        // ==========================================
        $voucherCode = null;
        if ($actionData['action'] === 'check_in') {
            try {
                // جلب الـ ID الخاص بسجل حضور اليوم لربطه بالكود
                $logId = AttendanceLog::where('employee_id', $employee->id)->where('date', $date)->value('id');

                if ($logId) {
                    // استدعاء خدمة الأكواد بشكل آمن
                    $voucher = app(\App\Modules\HR\Services\InternetVoucherService::class)
                        ->assignAutoVoucher($employee->id, $logId);

                    $voucherCode = $voucher->code;
                }
            } catch (\Exception $e) {
                // 🌟 كتم الخطأ برمجياً حتى لا ينهار تسجيل الحضور، وتسجيله في ملف الـ Log لمدير النظام
                \Illuminate\Support\Facades\Log::warning('فشل صرف كود إنترنت آلي للموظف ' . $employee->id . ': ' . $e->getMessage());
            }
        }

        return [
            'status'  => 'success',
            'action'  => $actionData['action'],
            'message' => $actionData['message'],
            'voucher' => $voucherCode // 🌟 إرسال الكود للواجهة الأمامية
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
     * 🚀 دالة العزل الرياضي: تكتشف البصمات المكررة حتى لو حدثت إحداها قبل منتصف الليل بدقيقة والأخرى بعده
     */
    private function isDuplicatePunch(Carbon $punchTime, string $storedTimeStr): bool
    {
        // نركب الوقت المخزن على تاريخ لحظة البصمة الحالية لنوحد المعيار الزمني
        $existingTime = Carbon::parse($punchTime->toDateString() . ' ' . $storedTimeStr);

        // إذا كان الفارق بينهما ضخماً (أكثر من 12 ساعة)، هذا يعني أن إحدى البصمتين في يوم والأخرى في اليوم التالي
        if ($existingTime->diffInMinutes($punchTime) > 720) {
            if ($existingTime->greaterThan($punchTime)) {
                $existingTime->subDay();
            } else {
                $existingTime->addDay();
            }
        }

        // الآن نقارن الفارق الحقيقي، إذا كان أقل من 5 دقائق فهي بصمة مكررة (Spam)
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

        // إذا كان لديه بصمة دخول، وكانت البصمة الجديدة بعد أكثر من ساعة، نعتبرها انصراف
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
