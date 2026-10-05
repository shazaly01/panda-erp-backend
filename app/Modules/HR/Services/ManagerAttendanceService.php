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
     * حقن الخدمة الأساسية للحضور للاستفادة من محرك الحسابات
     */
    public function __construct(AttendanceService $attendanceService)
    {
        $this->attendanceService = $attendanceService;
    }

    /**
     * استخراج كافة معرفات الأقسام الخاضعة لإشراف المشرف بما يشمل فروعها التابعة هرمياً
     */
    private function getSupervisedDepartmentIds(Employee $manager): array
    {
        $departmentIds = collect();

        foreach ($manager->supervisedDepartments as $department) {
            $departmentIds->push($department->id);

            // جلب معرفات كافة الأقسام الفرعية التابعة للقسم هرمياً
            $descendantIds = $department->descendants()->pluck('id');
            $departmentIds = $departmentIds->merge($descendantIds);
        }

        return $departmentIds->unique()->values()->all();
    }

    /**
     * جلب مصفوفة الحضور اليومية لفريق المشرف مع تطبيق الفلاتر الديناميكية
     */
    public function getTeamDailyMatrix(int $managerEmployeeId, array $filters): Collection
    {
        $manager = Employee::with('supervisedDepartments')->find($managerEmployeeId);

        if (!$manager || $manager->supervisedDepartments->isEmpty()) {
            return collect();
        }

        $supervisedDepartmentIds = $this->getSupervisedDepartmentIds($manager);

        if (empty($supervisedDepartmentIds)) {
            return collect();
        }

        $date = $filters['date'] ?? now()->toDateString();
        $search = $filters['search'] ?? null;
        $positionId = $filters['position_id'] ?? null;
        $status = $filters['status'] ?? null;

        $query = Employee::with([
            'position',
            'department',
            'attendanceLogs' => function ($query) use ($date) {
                $query->where('date', $date);
            }
        ])
        ->whereIn('department_id', $supervisedDepartmentIds);

        if (!empty($search)) {
            $query->where('full_name', 'like', '%' . $search . '%');
        }

        if (!empty($positionId)) {
            $query->where('position_id', $positionId);
        }

        if (!empty($status)) {
            if ($status === 'present') {
                $query->whereHas('attendanceLogs', function ($q) use ($date) {
                    $q->where('date', $date)->where('status', 'present');
                });
            } elseif ($status === 'late') {
                $query->whereHas('attendanceLogs', function ($q) use ($date) {
                    $q->where('date', $date)->where('status', 'late');
                });
            } elseif ($status === 'absent') {
                $query->where(function ($q) use ($date) {
                    $q->whereDoesntHave('attendanceLogs', function ($subQ) use ($date) {
                        $subQ->where('date', $date);
                    })->orWhereHas('attendanceLogs', function ($subQ) use ($date) {
                        $subQ->where('date', $date)->where('status', 'absent');
                    });
                });
            }
        }

        return $query->get()->map(function ($employee) {
            $todayLog = $employee->attendanceLogs->first();
            unset($employee->attendanceLogs);
            $employee->today_attendance = $todayLog;
            return $employee;
        });
    }

    /**
     * تعديل أو إدخال سجل الحضور يدوياً بواسطة المشرف (مع دعم الساعات الإضافية المعتمدة)
     */
    public function overrideTeamAttendance(
        int $managerEmployeeId,
        int $targetEmployeeId,
        string $date,
        ?string $checkIn,
        ?string $checkOut,
        string $reason,
        ?int $manualOvertimeMinutes = null
    ): AttendanceLog {
        $manager = Employee::with('supervisedDepartments')->find($managerEmployeeId);

        if (!$manager || $manager->supervisedDepartments->isEmpty()) {
            throw new Exception("غير مصرح لك، فأنت لست مشرفاً على أي قسم في النظام.");
        }

        $supervisedDepartmentIds = $this->getSupervisedDepartmentIds($manager);

        $employee = Employee::where('id', $targetEmployeeId)
            ->whereIn('department_id', $supervisedDepartmentIds)
            ->first();

        if (!$employee) {
            throw new Exception("غير مصرح لك بتعديل حضور هذا الموظف، فهو لا يعمل في أي قسم يقع تحت إشرافك.");
        }

        // استخدام محرك الحضور وتمرير الدقائق الإضافية المعتمدة من المشرف
        $log = $this->attendanceService->processDailyAttendance(
            $employee,
            $date,
            $checkIn,
            $checkOut,
            $manualOvertimeMinutes
        );

        // توثيق التدخل اليدوي (Audit Trail)
        $log->update([
            'is_manual_override' => true,
            'approved_by' => $managerEmployeeId,
            'override_reason' => $reason,
        ]);

        return $log;
    }
}