<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Modules\HR\Http\Resources\Reports\EmployeeAttendanceReportResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class AttendanceReportController extends Controller
{
    /**
     * جلب تقرير خلاصة الحضور والانصراف للموظفين بناءً على البيانات الفعلية والسجلات المخزنة
     */
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        // التحقق من المدخلات لسلامة الاستعلام
        $request->validate([
            'start_date'      => ['required', 'date', 'date_format:Y-m-d'],
            'end_date'        => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'employee_id'     => ['nullable', 'integer'],
            'department_id'   => ['nullable', 'integer'],
            'position_id'     => ['nullable', 'integer'],
            'pay_group_id'    => ['nullable', 'integer'],
            'employment_type' => ['nullable', 'string'],
            'present_only'    => ['nullable', 'boolean'],
            'search'          => ['nullable', 'string'],
        ]);

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // ضبط حدود الوقت لضمان شمول اليوم الأخير بالكامل واستغلال الـ Index المباشر
        $startBoundary = $startDate . ' 00:00:00';
        $endBoundary = $endDate . ' 23:59:59';

        // تعبير رياضي موحد ودقيق لحساب دقائق العمل من نوع TIME بدون مشاكل NULL
        $workMinutesExpression = "
            IFNULL(SUM(
                CASE
                    WHEN al.check_in IS NOT NULL AND al.check_out IS NOT NULL THEN
                        ROUND(MOD(TIME_TO_SEC(TIMEDIFF(al.check_out, al.check_in)) + 86400, 86400) / 60)
                    WHEN al.check_in IS NOT NULL AND al.check_out IS NULL AND al.status IN ('present', 'late') THEN
                        COALESCE(
                            CASE
                                WHEN s.start_time IS NOT NULL AND s.end_time IS NOT NULL THEN
                                    ROUND(MOD(TIME_TO_SEC(TIMEDIFF(s.end_time, s.start_time)) + 86400, 86400) / 60)
                            END,
                            480
                        )
                    ELSE 0
                END
            ), 0)
        ";

        // تنفيذ الاستعلام التجميعي الرئيسي للتقرير
        $query = DB::table('employees as e')
            ->leftJoin('hr_attendance_logs as al', function ($join) use ($startBoundary, $endBoundary) {
                $join->on('e.id', '=', 'al.employee_id')
                    ->whereBetween('al.date', [$startBoundary, $endBoundary])
                    ->whereNull('al.deleted_at');
            })
            ->leftJoin('departments as d', 'e.department_id', '=', 'd.id')
            ->leftJoin('hr_shifts as s', 'al.shift_id', '=', 's.id')
            // 🌟 الربط مع عقد الموظف النشط لجلب طريقة الدفع
            ->leftJoin('contracts as c', function ($join) {
                $join->on('e.id', '=', 'c.employee_id')
                    ->where('c.is_active', '=', true)
                    ->whereNull('c.deleted_at');
            })
            ->select(
                'e.id as employee_id',
                'e.employee_number',
                'e.full_name',
                'd.name as department_name',

                DB::raw("COUNT(CASE WHEN al.status IN ('present', 'late') THEN 1 END) as present_days"),
                DB::raw("COUNT(CASE WHEN al.status = 'late' THEN 1 END) as late_days"),
                DB::raw("COUNT(CASE WHEN al.status = 'absent' THEN 1 END) as absent_days"),
                DB::raw("COUNT(CASE WHEN al.status = 'on_leave' THEN 1 END) as leave_days"),

                DB::raw("IFNULL(SUM(al.delay_minutes), 0) as total_delay_minutes"),
                DB::raw("IFNULL(SUM(al.early_leave_minutes), 0) as total_early_leave_minutes"),
                DB::raw("IFNULL(SUM(al.overtime_minutes), 0) as total_overtime_minutes"),

                DB::raw("{$workMinutesExpression} as total_work_minutes")
            )
            ->whereNull('e.deleted_at');

        // --- تطبيق الفلاتر الديناميكية ---
        if ($request->filled('employee_id')) {
            $query->where('e.id', '=', (int) $request->input('employee_id'));
        }

        if ($request->filled('department_id')) {
            $departmentId = (int) $request->input('department_id');

            $dept = DB::table('departments')
                ->where('id', $departmentId)
                ->whereNull('deleted_at')
                ->first();

            if ($dept && isset($dept->_lft, $dept->_rgt)) {
                $departmentIds = DB::table('departments')
                    ->where('_lft', '>=', $dept->_lft)
                    ->where('_rgt', '<=', $dept->_rgt)
                    ->whereNull('deleted_at')
                    ->pluck('id')
                    ->toArray();

                $query->whereIn('e.department_id', $departmentIds);
            } else {
                $query->where('e.department_id', '=', $departmentId);
            }
        }

        if ($request->filled('position_id')) {
            $query->where('e.position_id', '=', (int) $request->input('position_id'));
        }

        // 🌟 تطبيق التصفية بحقل pay_group_id الخاص بجدول العقود
        if ($request->filled('pay_group_id')) {
            $query->where('c.pay_group_id', '=', (int) $request->input('pay_group_id'));
        }

        if ($request->filled('employment_type')) {
            $query->where('e.employment_type', '=', $request->input('employment_type'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('e.full_name', 'like', '%' . $search . '%')
                  ->orWhere('e.employee_number', 'like', '%' . $search . '%');
            });
        }

        $query->groupBy('e.id', 'e.employee_number', 'e.full_name', 'd.name');

        if ($request->boolean('present_only')) {
            $query->havingRaw("COUNT(CASE WHEN al.status IN ('present', 'late') THEN 1 END) > 0");
        }

        $results = $query->orderBy('e.employee_number')->get();

        return EmployeeAttendanceReportResource::collection($results);
    }
}
