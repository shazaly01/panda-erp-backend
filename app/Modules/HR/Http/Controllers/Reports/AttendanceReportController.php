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
     * جلب تقرير خلاصة الحضور والانصراف للموظفين مباشرة من قاعدة البيانات مع نظام حماية الساعات الافتراضية
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
            'employment_type' => ['nullable', 'string'],
            'present_only'    => ['nullable', 'boolean'],
            'search'          => ['nullable', 'string'],
        ]);

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // قراءة نمط الحساب الخاص بالشركة من ملف الإعدادات
        $attendanceMode = env('ATTENDANCE_MODE', 'strict');

        // بناء العقل الرياضي للاستعلام بناءً على نمط الشركة
        if ($attendanceMode === 'single_punch') {
            // نمط البصمة الواحدة: بمجرد أن تكون الحالة حضور أو تأخير، يتم احتساب ساعات الوردية.
            // حماية هندسية (COALESCE): إذا كانت الوردية غير موجودة أو أوقاتها فارغة، يتم احتساب 8 ساعات (480 دقيقة) تلقائياً لمنع التصفير.
            $workMinutesExpression = "
                IFNULL(SUM(
                    CASE
                        WHEN al.status IN ('present', 'late') THEN
                            COALESCE(
                                CASE
                                    WHEN s.start_time IS NOT NULL AND s.end_time IS NOT NULL THEN
                                        CASE
                                            WHEN s.end_time >= s.start_time THEN TIMESTAMPDIFF(MINUTE, s.start_time, s.end_time)
                                            ELSE (TIMESTAMPDIFF(MINUTE, s.start_time, '23:59:59') + TIMESTAMPDIFF(MINUTE, '00:00:00', s.end_time) + 1)
                                        END
                                END,
                                480
                            )
                        ELSE 0
                    END
                ), 0)
            ";
        } else {
            // نمط البصمتين الصارم (Strict): احتساب الفارق الدقيق والفعلي بين حركتي الدخول والخروج
            $workMinutesExpression = "
                IFNULL(SUM(
                    CASE
                        WHEN al.check_in IS NOT NULL AND al.check_out IS NOT NULL THEN
                            CASE
                                WHEN al.check_out >= al.check_in THEN TIMESTAMPDIFF(MINUTE, al.check_in, al.check_out)
                                ELSE (TIMESTAMPDIFF(MINUTE, al.check_in, '23:59:59') + TIMESTAMPDIFF(MINUTE, '00:00:00', al.check_out) + 1)
                            END
                        ELSE 0
                    END
                ), 0)
            ";
        }

        // تنفيذ الاستعلام التجميعي الرئيسي
        $query = DB::table('employees as e')
            ->leftJoin('hr_attendance_logs as al', function ($join) use ($startDate, $endDate) {
                $join->on('e.id', '=', 'al.employee_id')
                    ->whereBetween('al.date', [$startDate, $endDate])
                    ->whereNull('al.deleted_at');
            })
            ->leftJoin('departments as d', 'e.department_id', '=', 'd.id')
            ->leftJoin('hr_shifts as s', 'al.shift_id', '=', 's.id')
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

                // حقن التعبير الرياضي المحمي والديناميكي
                DB::raw("{$workMinutesExpression} as total_work_minutes")
            )
            ->whereNull('e.deleted_at');

        // تطبيق الفلاتر الديناميكية
        if ($request->filled('employee_id')) {
            $query->where('e.id', '=', (int) $request->input('employee_id'));
        }

        if ($request->filled('department_id')) {
            $query->where('e.department_id', '=', (int) $request->input('department_id'));
        }

        if ($request->filled('position_id')) {
            $query->where('e.position_id', '=', (int) $request->input('position_id'));
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
