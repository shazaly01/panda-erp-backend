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
     * جلب مصفوفة الحضور اليومية لفريق المشرف مع تطبيق الفلاتر الديناميكية
     * (البحث بالاسم، الوظيفة، وحالة التواجد: حاضر، لم يحضر، متأخر)
     */
    public function getTeamDailyMatrix(int $managerEmployeeId, array $filters): Collection
    {
        // 1. جلب بيانات المشرف مع الأقسام التي يشرف عليها
        $manager = Employee::with('supervisedDepartments')->find($managerEmployeeId);

        // إذا لم يكن الموظف مشرفاً على أي قسم، نرجع مصفوفة فارغة مباشرة
        if (!$manager || $manager->supervisedDepartments->isEmpty()) {
            return collect();
        }

        // استخراج أرقام الأقسام الخاضعة للإشراف
        $supervisedDepartmentIds = $manager->supervisedDepartments->pluck('id')->toArray();

        // استخراج قيم الفلاتر المرسلة مع تعيين قيم افتراضية عند الحاجة
        $date = $filters['date'] ?? now()->toDateString();
        $search = $filters['search'] ?? null;
        $positionId = $filters['position_id'] ?? null;
        $status = $filters['status'] ?? null;

        // 2. بناء الاستعلام الأساسي لجلب الموظفين التابعين للأقسام المحددة
        $query = Employee::with([
            'position',
            'department',
            'attendanceLogs' => function ($query) use ($date) {
                $query->where('date', $date);
            }
        ])
        ->whereIn('department_id', $supervisedDepartmentIds);

        // [فلتر البحث بالاسم]
        if (!empty($search)) {
            $query->where('full_name', 'like', '%' . $search . '%');
        }

        // [فلتر الوظيفة]
        if (!empty($positionId)) {
            $query->where('position_id', $positionId);
        }

        // [فلتر حالة التواجد] (حضر، لم يحضر "بدون بصمة"، متأخر)
        if (!empty($status)) {
            if ($status === 'present') {
                // الموظفون الذين لديهم سجل حضور في هذا اليوم وحالتهم "حاضر"
                $query->whereHas('attendanceLogs', function ($q) use ($date) {
                    $q->where('date', $date)->where('status', 'present');
                });
            } elseif ($status === 'late') {
                // الموظفون الذين لديهم سجل حضور في هذا اليوم وحالتهم "متأخر"
                $query->whereHas('attendanceLogs', function ($q) use ($date) {
                    $q->where('date', $date)->where('status', 'late');
                });
            } elseif ($status === 'absent') {
                // الموظفون الذين لم يحضروا (ليس لديهم سجل إطلاقاً في هذا اليوم، أو حالتهم مسجلة كـ غياب)
                $query->where(function ($q) use ($date) {
                    $q->whereDoesntHave('attendanceLogs', function ($subQ) use ($date) {
                        $subQ->where('date', $date);
                    })->orWhereHas('attendanceLogs', function ($subQ) use ($date) {
                        $subQ->where('date', $date)->where('status', 'absent');
                    });
                });
            }
        }

        // 3. تنفيذ الاستعلام ومعالجة النتيجة لربط السجل اليومي مباشرة بالموظف
        return $query->get()->map(function ($employee) {
            // استخراج السجل اليومي إذا وجد، أو إرجاع null
            $todayLog = $employee->attendanceLogs->first();

            // إزالة العلاقة المجمعة لتخفيف حجم الـ Response المرسل للواجهة
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
