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
     * جلب تقرير خلاصة الحضور والانصراف للموظفين مباشرة من قاعدة البيانات
     */
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        // التحقق من المدخلات الأساسية للتقرير لضمان سلامة الاستعلام
        $request->validate([
            'start_date'    => ['required', 'date', 'date_format:Y-m-d'],
            'end_date'      => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'employee_id'   => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
        ]);

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // بناء استعلام تجميعي مباشر فائق السرعة
        $query = DB::table('employees as e')
            ->leftJoin('hr_attendance_logs as al', function ($join) use ($startDate, $endDate) {
                $join->on('e.id', '=', 'al.employee_id')
                    ->whereBetween('al.date', [$startDate, $endDate])
                    ->whereNull('al.deleted_at');
            })
            ->leftJoin('departments as d', 'e.department_id', '=', 'd.id')
            ->select(
                'e.id as employee_id',
                'e.employee_number',
                'e.full_name',
                'd.name as department_name',

                // حساب عدد أيام الحالات المختلفة
                DB::raw("COUNT(CASE WHEN al.status = 'present' THEN 1 END) as present_days"),
                DB::raw("COUNT(CASE WHEN al.status = 'late' THEN 1 END) as late_days"),
                DB::raw("COUNT(CASE WHEN al.status = 'absent' THEN 1 END) as absent_days"),
                DB::raw("COUNT(CASE WHEN al.status = 'on_leave' THEN 1 END) as leave_days"),

                // تجميع الدقائق الإجمالية للعجز والإضافي
                DB::raw("IFNULL(SUM(al.delay_minutes), 0) as total_delay_minutes"),
                DB::raw("IFNULL(SUM(al.early_leave_minutes), 0) as total_early_leave_minutes"),
                DB::raw("IFNULL(SUM(al.overtime_minutes), 0) as total_overtime_minutes"),

                // الحساب الرياضي الذكي لصافي دقائق العمل الفعلية بين الدخول والخروج للورديات العادية والليلية
                DB::raw("IFNULL(SUM(
                    CASE
                        WHEN al.check_in IS NOT NULL AND al.check_out IS NOT NULL THEN
                            CASE
                                WHEN al.check_out >= al.check_in THEN TIMESTAMPDIFF(MINUTE, al.check_in, al.check_out)
                                ELSE (TIMESTAMPDIFF(MINUTE, al.check_in, '23:59:59') + TIMESTAMPDIFF(MINUTE, '00:00:00', al.check_out) + 1)
                            END
                        ELSE 0
                    END
                ), 0) as total_work_minutes")
            )
            ->whereNull('e.deleted_at');

        // تطبيق الفلاتر الديناميكية للبحث
        if ($request->filled('employee_id')) {
            $query->where('e.id', '=', (int) $request->input('employee_id'));
        }

        if ($request->filled('department_id')) {
            $query->where('e.department_id', '=', (int) $request->input('department_id'));
        }

        // التجميع النهائي والترتيب بحسب الرقم الوظيفي لضمان سرعة الفهرسة
        $results = $query->groupBy('e.id', 'e.employee_number', 'e.full_name', 'd.name')
            ->orderBy('e.employee_number')
            ->get();

        // إرجاع البيانات مغلفة بالريسورس المخصص للتقرير
        return EmployeeAttendanceReportResource::collection($results);
    }
}
