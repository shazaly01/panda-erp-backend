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



    /**
     * معالجة البصمات التلقائية (مثل الباركود) بناءً على نقطة منتصف الوردية (Midpoint Logic)
     */
    /**
     * معالجة البصمات التلقائية (مثل الباركود) بناءً على نقطة منتصف الوردية (Midpoint Logic)
     * وتدعم المعالجة الذكية للورديات المسائية (Night Shift Paradox)
     */
    public function processAutoPunch(Employee $employee, Carbon $punchTime): array
    {
        $physicalDate = $punchTime->toDateString();
        $logicalDate = $physicalDate; // الافتراضي هو اليوم الحالي

        // ==========================================
        // 1. الذكاء الاصطناعي لمعالجة الوردية الليلية
        // ==========================================
        $yesterday = $punchTime->copy()->subDay()->toDateString();

        // التحقق مما إذا كان لديه وردية بالأمس
        $yesterdayShiftData = EmployeeShift::with('shift')
            ->where('employee_id', $employee->id)
            ->where('start_date', '<=', $yesterday)
            ->where(function ($query) use ($yesterday) {
                $query->whereNull('end_date')->orWhere('end_date', '>=', $yesterday);
            })->first();

        if ($yesterdayShiftData && $yesterdayShiftData->shift) {
            $yShift = $yesterdayShiftData->shift;

            // هل وردية الأمس كانت مسائية؟ (وقت النهاية أصغر من وقت البداية)
            if (Carbon::parse($yShift->end_time)->lessThan(Carbon::parse($yShift->start_time))) {

                // حساب وقت نهاية وردية الأمس الفعلي (الذي يقع في اليوم الحالي)
                $yShiftEnd = Carbon::parse($yesterday . ' ' . $yShift->end_time)->addDay();

                // إضافة فترة سماح للوردية الليلية (مثلاً 4 ساعات بعد النهاية تحسباً للانصراف المتأخر جداً)
                $maxCheckOutTime = $yShiftEnd->copy()->addHours(4);

                // إذا كانت البصمة الحالية حدثت قبل انتهاء فترة السماح، إذن هي تتبع ليوم الأمس!
                if ($punchTime->lessThanOrEqualTo($maxCheckOutTime)) {
                    $logicalDate = $yesterday;
                }
            }
        }

        $date = $logicalDate; // اعتماد التاريخ المنطقي لباقي العمليات

        // ==========================================
        // 2. جلب وردية الموظف بالتاريخ المنطقي الصحيح
        // ==========================================
        $employeeShift = EmployeeShift::with('shift')
            ->where('employee_id', $employee->id)
            ->where('start_date', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('end_date')->orWhere('end_date', '>=', $date);
            })
            ->first();

        if (!$employeeShift || !$employeeShift->shift) {
            throw new Exception("لا توجد وردية مسجلة للموظف في تاريخ " . $date);
        }

        $shift = $employeeShift->shift;

        // ==========================================
        // 3. حساب نقطة المنتصف (Midpoint Logic)
        // ==========================================
        $shiftStart = Carbon::parse($date . ' ' . $shift->start_time);
        $shiftEnd = Carbon::parse($date . ' ' . $shift->end_time);

        // تعديل نهاية الوردية إذا كانت تمتد لليوم التالي
        if ($shiftEnd->lessThan($shiftStart)) {
            $shiftEnd->addDay();
        }

        $shiftDuration = $shiftStart->diffInMinutes($shiftEnd);
        $midPoint = $shiftStart->copy()->addMinutes($shiftDuration / 2);

        // ==========================================
        // 4. جلب سجل الحضور إن وجد (لحفظ البصمات السابقة)
        // ==========================================
        $todayLog = AttendanceLog::where('employee_id', $employee->id)
            ->where('date', $date)
            ->first();

        $checkInTime = $todayLog ? $todayLog->check_in : null;
        $checkOutTime = $todayLog ? $todayLog->check_out : null;

        // ==========================================
        // 5. تحديد نوع البصمة (دخول أم خروج؟)
        // ==========================================
        $isCheckIn = $punchTime->lessThan($midPoint);

        if ($isCheckIn) {
            // --- سيناريو تسجيل الدخول ---
            if ($checkInTime) {
                // منع تكرار البصمة بالخطأ خلال 5 دقائق
                $existingCheckIn = Carbon::parse($date . ' ' . $checkInTime);
                if ($punchTime->diffInMinutes($existingCheckIn) < 5) {
                    return [
                        'status' => 'warning',
                        'action' => 'ignored',
                        'message' => 'تم تسجيل حضورك بالفعل قبل قليل.'
                    ];
                }
            } else {
                // اعتماد وقت الحضور
                $checkInTime = $punchTime->toTimeString();
            }
            $action = 'check_in';
            $message = 'أهلاً بك، تم تسجيل الحضور بنجاح.';

        } else {
            // --- سيناريو تسجيل الخروج ---
            if ($checkOutTime) {
                // منع تكرار بصمة الخروج بالخطأ خلال 5 دقائق
                // نستخدم physicalDate هنا لأن بصمة الخروج قد تكون في اليوم التالي فعلياً
                $existingCheckOut = Carbon::parse($physicalDate . ' ' . $checkOutTime);
                if ($punchTime->diffInMinutes($existingCheckOut) < 5) {
                    return [
                        'status' => 'warning',
                        'action' => 'ignored',
                        'message' => 'تم تسجيل انصرافك بالفعل قبل قليل.'
                    ];
                }
            }
            // اعتماد وقت الانصراف (يتم تحديثه دائماً بآخر بصمة يضعها الموظف قبل مغادرته)
            $checkOutTime = $punchTime->toTimeString();
            $action = 'check_out';
            $message = 'رافقتك السلامة، تم تسجيل الانصراف.';
        }

        // ==========================================
        // 6. الحفظ النهائي في قاعدة البيانات
        // ==========================================
        $this->processDailyAttendance(
            $employee,
            $date, // نرسل التاريخ المنطقي للخدمة لتسجل في اليوم الصحيح
            $checkInTime,
            $checkOutTime
        );

        return [
            'status'  => 'success',
            'action'  => $action,
            'message' => $message
        ];
    }
}
