<?php

declare(strict_types=1);

namespace App\Modules\HR\Services;

use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\AttendanceLog;
use Illuminate\Support\Collection;
use Exception;

class ManagerAttendanceService
{
    private AttendanceService $attendanceService;

    /**
     * حقن الخدمة الأساسية للحضور للاستفادة من محرك الحسابات الخاص بك
     */
    public function __construct(AttendanceService $attendanceService)
    {
        $this->attendanceService = $attendanceService;
    }

    /**
     * جلب مصفوفة الحضور اليومية لفريق المشرف (تظهر الجميع: من بصم ومن غاب)
     * التعديل: جلب كل الموظفين في الأقسام التي يشرف عليها هذا المستخدم
     */
    public function getTeamDailyMatrix(int $managerEmployeeId, string $date): Collection
    {
        // 1. جلب بيانات المشرف مع الأقسام التي يشرف عليها
        $manager = Employee::with('supervisedDepartments')->find($managerEmployeeId);

        // إذا لم يكن الموظف مشرفاً على أي قسم، نرجع مصفوفة فارغة مباشرة
        if (!$manager || $manager->supervisedDepartments->isEmpty()) {
            return collect();
        }

        // استخراج أرقام الأقسام
        $supervisedDepartmentIds = $manager->supervisedDepartments->pluck('id')->toArray();

        // 2. نجلب جميع الموظفين التابعين لهذه الأقسام
        // ونستخدم Eager Loading لجلب سجل الحضور الخاص بهم لهذا التاريخ المحددة فقط
        return Employee::with([
            'position',
            'department',
            'attendanceLogs' => function ($query) use ($date) {
                $query->where('date', $date);
            }
        ])
        ->whereIn('department_id', $supervisedDepartmentIds)
        ->get()
        ->map(function ($employee) {
            // استخراج السجل اليومي إذا وجد، أو إرجاع null
            $todayLog = $employee->attendanceLogs->first();

            // إزالة العلاقة المجمعة حتى لا يثقل الـ Response
            unset($employee->attendanceLogs);

            // ربط السجل كخاصية مباشرة (ستكون null لمن لم يبصم)
            $employee->today_attendance = $todayLog;

            return $employee;
        });
    }

    /**
     * تعديل أو إدخال سجل الحضور يدوياً بواسطة المشرف
     */
    public function overrideTeamAttendance(
        int $managerEmployeeId,
        int $targetEmployeeId,
        string $date,
        ?string $checkIn,
        ?string $checkOut,
        string $reason
    ): AttendanceLog {
        // 1. التحقق الأمني: هل الموظف المستهدف يعمل في قسم يشرف عليه هذا المدير؟
        $manager = Employee::with('supervisedDepartments')->find($managerEmployeeId);

        if (!$manager || $manager->supervisedDepartments->isEmpty()) {
            throw new Exception("غير مصرح لك، فأنت لست مشرفاً على أي قسم في النظام.");
        }

        $supervisedDepartmentIds = $manager->supervisedDepartments->pluck('id')->toArray();

        $employee = Employee::where('id', $targetEmployeeId)
            ->whereIn('department_id', $supervisedDepartmentIds)
            ->first();

        if (!$employee) {
            throw new Exception("غير مصرح لك بتعديل حضور هذا الموظف، فهو لا يعمل في أي قسم يقع تحت إشرافك.");
        }

        // 2. استخدام محرك الحضور الأساسي الخاص بك لضمان دقة الحسابات (تأخير، إضافي، ورادي)
        $log = $this->attendanceService->processDailyAttendance(
            $employee,
            $date,
            $checkIn,
            $checkOut
        );

        // 3. توثيق التدخل اليدوي (Audit Trail)
        $log->update([
            'is_manual_override' => true,
            'approved_by' => $managerEmployeeId,
            'override_reason' => $reason,
        ]);

        return $log;
    }
}
