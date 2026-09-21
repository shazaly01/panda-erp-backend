<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Modules\HR\Enums\EmploymentType;
use App\Modules\HR\Models\AttendanceLog;
use App\Modules\HR\Models\Department;
use App\Modules\HR\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DepartmentMonthlyAttendanceReportController extends Controller
{
    /**
     * توليد تقرير الحضور الشهري لجميع موظفي قسم معين
     * يعرض كل موظف وتحته سجله الشهري وملخص إحصائياته
     * الشهر الحالي هو الافتراضي ما لم يتم تحديد تواريخ أخرى
     */
    public function __invoke(Request $request): JsonResponse
    {
        // 1. التحقق من صحة المدخلات
        $data = $request->validate([
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'month'         => ['nullable', 'date_format:Y-m'],
            'start_date'    => ['nullable', 'date', 'date_format:Y-m-d'],
            'end_date'      => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        // 2. ضبط النطاق الزمني
        if (!empty($data['start_date']) && !empty($data['end_date'])) {
            $startDate = $data['start_date'];
            $endDate = $data['end_date'];
            $monthTitle = Carbon::parse($startDate)->locale('ar')->translatedFormat('F Y');
        } elseif (!empty($data['month'])) {
            $carbonMonth = Carbon::createFromFormat('Y-m', $data['month']);
            $startDate = $carbonMonth->copy()->startOfMonth()->toDateString();
            $endDate = $carbonMonth->copy()->endOfMonth()->toDateString();
            $monthTitle = $carbonMonth->locale('ar')->translatedFormat('F Y');
        } else {
            $startDate = Carbon::today()->startOfMonth()->toDateString();
            $endDate = Carbon::today()->endOfMonth()->toDateString();
            $monthTitle = Carbon::today()->locale('ar')->translatedFormat('F Y');
        }

        // 3. جلب القسم والتحقق من الصلاحيات
        $department = Department::findOrFail((int) $data['department_id']);

        $user = Auth::guard('sanctum')->user() ?? Auth::user();
        if ($user && !$user->can('hr.attendance.manage')) {
            $isSupervisor = $department->supervisors()
                ->where('employees.id', $user->employee_id)
                ->exists();

            if (!$isSupervisor) {
                return response()->json([
                    'message' => 'غير مصرح لك بالاطلاع على تقارير هذا القسم.'
                ], 403);
            }
        }

        // 4. جلب معرفات القسم وأقسامه التابعة هرمياً
        $departmentIds = method_exists($department, 'descendantsAndSelf')
            ? $department->descendantsAndSelf()->pluck('id')->all()
            : $department->descendants()->pluck('id')->push($department->id)->all();

        // 5. جلب موظفي القسم مع استبعاد المتدربين والموظفين المنتهية خدماتهم بأمان
        $employees = Employee::withoutGlobalScope('exclude_interns')
            ->whereIn('department_id', $departmentIds)
            ->where(function ($q) {
                $q->whereNull('employment_type')
                  ->orWhere('employment_type', '!=', EmploymentType::Intern->value);
            })
            ->where(function ($q) {
                // استبعاد الحالات غير النشطة فقط مع قبول أي حالة أخرى أو القيم الفارغة
                $q->whereNull('status')
                  ->orWhereNotIn('status', ['terminated', 'resigned', 'archived']);
            })
            ->with(['department', 'position'])
            ->orderBy('full_name', 'asc')
            ->get();

        if ($employees->isEmpty()) {
            return response()->json([
                'message' => 'لا يوجد موظفون نشطون مسجلون في هذا القسم حالياً.',
                'department' => [
                    'id'   => $department->id,
                    'name' => $department->name,
                    'code' => $department->code,
                ],
                'filter' => [
                    'start_date'  => $startDate,
                    'end_date'    => $endDate,
                    'month_title' => $monthTitle,
                ],
                'employees' => [],
            ], 200);
        }

        $employeeIds = $employees->pluck('id')->all();

        // 6. جلب جميع سجلات الحضور لجميع الموظفين دفعة واحدة
        $logsGroupedByEmployee = AttendanceLog::with('shift')
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date', 'asc')
            ->get()
            ->groupBy('employee_id');

        $attendanceMode = env('ATTENDANCE_MODE', 'strict');

        // 7. معالجة وحساب بيانات كل موظف
        $departmentSummary = [
            'total_employees'           => $employees->count(),
            'total_days_logged'         => 0,
            'present_days'              => 0,
            'late_days'                 => 0,
            'absent_days'               => 0,
            'leave_days'                => 0,
            'total_delay_minutes'       => 0,
            'total_early_leave_minutes' => 0,
            'total_overtime_minutes'    => 0,
            'total_work_minutes'        => 0,
            'total_work_hours'          => 0,
        ];

        $employeesData = [];

        foreach ($employees as $employee) {
            $employeeLogs = $logsGroupedByEmployee->get($employee->id, collect());

            $employeeSummary = [
                'total_days_logged'         => 0,
                'present_days'              => 0,
                'late_days'                 => 0,
                'absent_days'               => 0,
                'leave_days'                => 0,
                'total_delay_minutes'       => 0,
                'total_early_leave_minutes' => 0,
                'total_overtime_minutes'    => 0,
                'total_work_minutes'        => 0,
                'total_work_hours'          => 0,
            ];

            $records = [];

            foreach ($employeeLogs as $log) {
                $carbonDate = Carbon::parse($log->date);

                // حساب وقت العمل الفعلي
                $workMinutes = 0;
                if ($log->check_in && $log->check_out) {
                    $inSeconds = strtotime($log->check_in);
                    $outSeconds = strtotime($log->check_out);
                    $diffSeconds = ($outSeconds - $inSeconds + 86400) % 86400;
                    $workMinutes = (int) round($diffSeconds / 60);
                } elseif ($log->check_in && !$log->check_out && in_array($log->status, ['present', 'late'], true)) {
                    if ($log->shift && $log->shift->start_time && $log->shift->end_time) {
                        $shiftStartSeconds = strtotime($log->shift->start_time);
                        $shiftEndSeconds = strtotime($log->shift->end_time);
                        $diffSeconds = ($shiftEndSeconds - $shiftStartSeconds + 86400) % 86400;
                        $workMinutes = (int) round($diffSeconds / 60);
                    } else {
                        $workMinutes = 480;
                    }
                }

                $earlyLeaveMinutes = $attendanceMode === 'single_punch' ? 0 : (int) $log->early_leave_minutes;
                $delayMinutes = (int) $log->delay_minutes;
                $overtimeMinutes = (int) $log->overtime_minutes;

                // تحديث ملخص الموظف
                $employeeSummary['total_days_logged']++;
                $employeeSummary['total_delay_minutes'] += $delayMinutes;
                $employeeSummary['total_early_leave_minutes'] += $earlyLeaveMinutes;
                $employeeSummary['total_overtime_minutes'] += $overtimeMinutes;
                $employeeSummary['total_work_minutes'] += $workMinutes;

                if (in_array($log->status, ['present', 'late'], true)) {
                    $employeeSummary['present_days']++;
                }
                if ($log->status === 'late') {
                    $employeeSummary['late_days']++;
                } elseif ($log->status === 'absent') {
                    $employeeSummary['absent_days']++;
                } elseif ($log->status === 'on_leave') {
                    $employeeSummary['leave_days']++;
                }

                $records[] = [
                    'id'                  => $log->id,
                    'date'                => $carbonDate->format('Y-m-d'),
                    'day_name'            => $carbonDate->locale('ar')->translatedFormat('l'),
                    'shift_name'          => $log->shift?->name,
                    'check_in'            => $log->check_in ? date('H:i', strtotime($log->check_in)) : null,
                    'check_out'           => $log->check_out ? date('H:i', strtotime($log->check_out)) : null,
                    'check_out_formatted' => $log->check_out
                        ? date('H:i', strtotime($log->check_out))
                        : ($attendanceMode === 'single_punch' ? 'غير مطلوب' : 'بصمة مفقودة'),
                    'status'              => $log->status,
                    'delay_minutes'       => $delayMinutes,
                    'early_leave_minutes' => $earlyLeaveMinutes,
                    'overtime_minutes'    => $overtimeMinutes,
                    'work_minutes'        => $workMinutes,
                    'work_hours'          => round($workMinutes / 60, 2),
                ];
            }

            $employeeSummary['total_work_hours'] = round($employeeSummary['total_work_minutes'] / 60, 2);

            // تجميع المجاميع للقسم ككل
            $departmentSummary['total_days_logged'] += $employeeSummary['total_days_logged'];
            $departmentSummary['present_days'] += $employeeSummary['present_days'];
            $departmentSummary['late_days'] += $employeeSummary['late_days'];
            $departmentSummary['absent_days'] += $employeeSummary['absent_days'];
            $departmentSummary['leave_days'] += $employeeSummary['leave_days'];
            $departmentSummary['total_delay_minutes'] += $employeeSummary['total_delay_minutes'];
            $departmentSummary['total_early_leave_minutes'] += $employeeSummary['total_early_leave_minutes'];
            $departmentSummary['total_overtime_minutes'] += $employeeSummary['total_overtime_minutes'];
            $departmentSummary['total_work_minutes'] += $employeeSummary['total_work_minutes'];

            $employeesData[] = [
                'employee' => [
                    'id'              => $employee->id,
                    'barcode'         => $employee->barcode,
                    'employee_number' => $employee->employee_number,
                    'phone'           => $employee->phone,
                    'full_name'       => $employee->full_name,
                    'department'      => $employee->department?->name,
                    'position'        => $employee->position?->name,
                    'employment_type' => $employee->employment_type?->value ?? $employee->employment_type,
                ],
                'summary' => $employeeSummary,
                'records' => $records,
            ];
        }

        $departmentSummary['total_work_hours'] = round($departmentSummary['total_work_minutes'] / 60, 2);

        // 8. إرجاع الاستجابة النهائية
        return response()->json([
            'department' => [
                'id'          => $department->id,
                'name'        => $department->name,
                'code'        => $department->code,
                'type'        => $department->type?->value ?? $department->type,
                'is_active'   => $department->is_active,
            ],
            'filter' => [
                'start_date'      => $startDate,
                'end_date'        => $endDate,
                'month_title'     => $monthTitle,
                'attendance_mode' => $attendanceMode,
            ],
            'department_summary' => $departmentSummary,
            'employees'          => $employeesData,
        ], 200);
    }
}