<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\AttendanceLog;
use App\Modules\HR\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmployeeDetailedAttendanceReportController extends Controller
{
    /**
     * توليد تقرير مفصل لحضور وانصراف موظف معين مقسماً حسب الأسبوع أو الشهر
     * يدعم البحث المرن عبر (الباركود، الرقم الوظيفي، أو الهاتف)
     * مع اعتماد بداية الأسبوع من يوم الأحد، ونطاق افتراضي لآخر شهر / 4 أسابيع
     */
    public function __invoke(Request $request): JsonResponse
    {
        // 1. التحقق المباشر من صحة المدخلات (التواريخ أصبحت اختيارية ولها قيم افتراضية)
        $data = $request->validate([
            'identifier'      => ['nullable', 'string'],
            'barcode'         => ['nullable', 'string'],
            'employee_number' => ['nullable', 'string'],
            'phone'           => ['nullable', 'string'],
            'employee_id'     => ['nullable', 'integer'],
            'start_date'      => ['nullable', 'date', 'date_format:Y-m-d'],
            'end_date'        => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'group_by'        => ['nullable', 'string', 'in:week,month'],
        ]);

        // استخلاص قيمة البحث من أي حقل تم إرساله
        $searchTerm = trim((string) (
            $data['identifier'] ??
            $data['barcode'] ??
            $data['employee_number'] ??
            $data['phone'] ??
            ''
        ));

        if (empty($searchTerm) && empty($data['employee_id'])) {
            return response()->json([
                'message' => 'يرجى إدخال الرقم الوظيفي، رقم الهاتف، أو مسح رمز الباركود للموظف.'
            ], 422);
        }

        // 2. البحث عن الموظف وتجاوز عزل المتدربين تلقائياً
        $employeeQuery = Employee::withInterns()->with(['department', 'position']);

        if (!empty($searchTerm)) {
            $employee = $employeeQuery->where(function ($query) use ($searchTerm) {
                $query->where('barcode', $searchTerm)
                      ->orWhere('employee_number', $searchTerm)
                      ->orWhere('phone', $searchTerm);
            })->first();

            if (!$employee) {
                return response()->json([
                    'message' => 'عذراً، لم يتم العثور على أي موظف يطابق البيانات المدخلة (باركود، رقم وظيفي، أو هاتف).'
                ], 404);
            }
        } else {
            $employee = $employeeQuery->findOrFail((int) $data['employee_id']);
        }

        // 3. تحديد نمط التقسيم وضبط التواريخ الافتراضية (آخر شهر أو آخر 4 أسابيع تبدأ من الأحد)
        $groupBy = $data['group_by'] ?? 'week';

        if (!empty($data['start_date']) && !empty($data['end_date'])) {
            $startDate = $data['start_date'];
            $endDate = $data['end_date'];
        } else {
            if ($groupBy === 'month') {
                // النطاق الافتراضي للشهري: آخر 30 يوماً حتى اليوم
                $startDate = Carbon::today()->subMonth()->startOfMonth()->toDateString();
                $endDate = Carbon::today()->toDateString();
            } else {
                // النطاق الافتراضي للأسبوعي: آخر 4 أسابيع كاملة تبدأ من يوم الأحد
                $startDate = Carbon::today()->subWeeks(3)->startOfWeek(Carbon::SUNDAY)->toDateString();
                $endDate = Carbon::today()->endOfWeek(Carbon::SATURDAY)->toDateString();
            }
        }

        $employeeId = $employee->id;

        // 4. التحقق من الصلاحيات إن كان الطلب صادراً من مستخدم مسجل الدخول داخل لوحة التحكم
        $user = Auth::guard('sanctum')->user() ?? Auth::user();
        if ($user && !$user->can('hr.attendance.manage') && (int) $user->employee_id !== $employeeId) {
            return response()->json([
                'message' => 'غير مصرح لك بالاطلاع على تقارير هذا الموظف.'
            ], 403);
        }

        // 5. جلب سجلات الحضور الخاصة بالموظف ضمن النطاق الزمني المحدد
        $logs = AttendanceLog::with('shift')
            ->where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date', 'asc')
            ->get();

        $attendanceMode = env('ATTENDANCE_MODE', 'strict');

        // 6. تجميع السجلات وحساب المجاميع الفرعية والكلية (مع فرض بداية الأسبوع من الأحد)
        $groupedData = [];

        $overallSummary = [
            'total_days_logged'         => 0,
            'present_days'              => 0,
            'late_days'                 => 0,
            'absent_days'               => 0,
            'leave_days'                => 0,
            'total_delay_minutes'       => 0,
            'total_early_leave_minutes' => 0,
            'total_overtime_minutes'    => 0,
            'total_work_minutes'        => 0,
        ];

        foreach ($logs as $log) {
            $carbonDate = Carbon::parse($log->date);

            // 🌟 ضبط بداية الأسبوع من الأحد ونهايته في السبت
            if ($groupBy === 'week') {
                $periodStart = $carbonDate->copy()->startOfWeek(Carbon::SUNDAY)->format('Y-m-d');
                $periodEnd = $carbonDate->copy()->endOfWeek(Carbon::SATURDAY)->format('Y-m-d');
                $periodKey = $periodStart . '_' . $periodEnd;
                $periodTitle = 'الأسبوع (' . $periodStart . ' إلى ' . $periodEnd . ')';
            } else {
                $periodKey = $carbonDate->format('Y-m');
                $periodStart = $carbonDate->copy()->startOfMonth()->format('Y-m-d');
                $periodEnd = $carbonDate->copy()->endOfMonth()->format('Y-m-d');
                $periodTitle = $carbonDate->locale('ar')->translatedFormat('F Y');
            }

            if (!isset($groupedData[$periodKey])) {
                $groupedData[$periodKey] = [
                    'period_key'   => $periodKey,
                    'period_title' => $periodTitle,
                    'start_date'   => $periodStart,
                    'end_date'     => $periodEnd,
                    'subtotal'     => [
                        'total_days'                => 0,
                        'present_days'              => 0,
                        'late_days'                 => 0,
                        'absent_days'               => 0,
                        'leave_days'                => 0,
                        'total_delay_minutes'       => 0,
                        'total_early_leave_minutes' => 0,
                        'total_overtime_minutes'    => 0,
                        'total_work_minutes'        => 0,
                    ],
                    'records' => [],
                ];
            }

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

            $groupedData[$periodKey]['subtotal']['total_days']++;
            $groupedData[$periodKey]['subtotal']['total_delay_minutes'] += $delayMinutes;
            $groupedData[$periodKey]['subtotal']['total_early_leave_minutes'] += $earlyLeaveMinutes;
            $groupedData[$periodKey]['subtotal']['total_overtime_minutes'] += $overtimeMinutes;
            $groupedData[$periodKey]['subtotal']['total_work_minutes'] += $workMinutes;

            if (in_array($log->status, ['present', 'late'], true)) {
                $groupedData[$periodKey]['subtotal']['present_days']++;
            }
            if ($log->status === 'late') {
                $groupedData[$periodKey]['subtotal']['late_days']++;
            } elseif ($log->status === 'absent') {
                $groupedData[$periodKey]['subtotal']['absent_days']++;
            } elseif ($log->status === 'on_leave') {
                $groupedData[$periodKey]['subtotal']['leave_days']++;
            }

            $overallSummary['total_days_logged']++;
            $overallSummary['total_delay_minutes'] += $delayMinutes;
            $overallSummary['total_early_leave_minutes'] += $earlyLeaveMinutes;
            $overallSummary['total_overtime_minutes'] += $overtimeMinutes;
            $overallSummary['total_work_minutes'] += $workMinutes;

            if (in_array($log->status, ['present', 'late'], true)) {
                $overallSummary['present_days']++;
            }
            if ($log->status === 'late') {
                $overallSummary['late_days']++;
            } elseif ($log->status === 'absent') {
                $overallSummary['absent_days']++;
            } elseif ($log->status === 'on_leave') {
                $overallSummary['leave_days']++;
            }

            $groupedData[$periodKey]['records'][] = [
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

        foreach ($groupedData as &$period) {
            $period['subtotal']['total_work_hours'] = round($period['subtotal']['total_work_minutes'] / 60, 2);
        }
        unset($period);

        $overallSummary['total_work_hours'] = round($overallSummary['total_work_minutes'] / 60, 2);

        return response()->json([
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
            'filter' => [
                'start_date'      => $startDate,
                'end_date'        => $endDate,
                'group_by'        => $groupBy,
                'attendance_mode' => $attendanceMode,
            ],
            'overall_summary' => $overallSummary,
            'periods'         => array_values($groupedData),
        ], 200);
    }
}