<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Currency;
use App\Modules\HR\Enums\EmployeeStatus;
use App\Modules\HR\Enums\EmploymentType;
use App\Modules\HR\Enums\PayrollRunType;
use App\Modules\HR\Models\AttendanceLog;
use App\Modules\HR\Models\Contract;
use App\Modules\HR\Models\Department;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\HrLeavePass;
use App\Modules\HR\Models\InternshipApplication;
use App\Modules\HR\Models\LeaveRequest;
use App\Modules\HR\Models\Loan;
use App\Modules\HR\Models\LoanInstallment;
use App\Modules\HR\Models\PayrollBatch;
use App\Modules\HR\Models\Payslip;
use App\Modules\HR\Models\Visitor;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HRDashboardController extends Controller
{
    /**
     * عرض البيانات التحليلية والإحصائية المركّزة للوحة تحكم موديول الموارد البشرية
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! $user->can('hr.dashboard.view')) {
            return response()->json([
                'message' => 'عذراً، ليس لديك صلاحية الوصول إلى لوحة تحكم الموارد البشرية.',
            ], 403);
        }

        $validated = $request->validate([
            'date_from'     => ['nullable', 'date'],
            'date_to'       => ['nullable', 'date', 'after_or_equal:date_from'],
            'department_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $scope = $this->resolvePeriodAndScope($validated);

        /** @var Carbon $startDate */
        $startDate = $scope['start_date'];
        /** @var Carbon $endDate */
        $endDate = $scope['end_date'];
        /** @var Carbon $prevStartDate */
        $prevStartDate = $scope['prev_start_date'];
        /** @var Carbon $prevEndDate */
        $prevEndDate = $scope['prev_end_date'];
        /** @var int|null $departmentId */
        $departmentId = $scope['department_id'];
        /** @var array<int, int>|null $departmentIds */
        $departmentIds = $scope['department_ids'];
        /** @var Currency|null $baseCurrency */
        $baseCurrency = $scope['base_currency'];

        // 1. بطاقات المؤشرات الرئيسية الخمسة (5 Core KPIs)
        $kpis = $this->buildKpis($startDate, $endDate, $prevStartDate, $prevEndDate, $departmentIds);

        // 2. الرسوم البيانية الأربعة الأساسية (4 Core Charts)
        $charts = [
            'payroll_trend'           => $this->buildPayrollTrendChart($startDate, $endDate, $departmentIds),
            'employees_by_department' => $this->buildEmployeesByDepartmentChart($departmentIds),
            'attendance_trend'        => $this->buildAttendanceTrendChart($startDate, $endDate, $departmentIds),
            'employees_by_status'     => $this->buildEmployeesByStatusChart($departmentIds),
        ];

        // 3. التنبيهات الإدارية والرقابية والقوائم السريعة
        $monitoring = $this->buildAlertsAndLists($startDate, $endDate, $departmentIds);

        return response()->json([
            'status' => true,
            'data'   => [
                'meta' => [
                    'date_from'     => $startDate->format('Y-m-d'),
                    'date_to'       => $endDate->format('Y-m-d'),
                    'department_id' => $departmentId,
                    'base_currency' => $baseCurrency ? [
                        'id'     => $baseCurrency->id,
                        'code'   => $baseCurrency->code,
                        'name'   => $baseCurrency->name,
                        'symbol' => $baseCurrency->symbol,
                    ] : [
                        'id'     => null,
                        'code'   => 'SDG',
                        'name'   => 'العملة الأساسية',
                        'symbol' => '',
                    ],
                ],
                'kpis'                   => $kpis,
                'charts'                 => $charts,
                'alerts'                 => $monitoring['alerts'],
                'recent_payroll_batches' => $monitoring['recent_payroll_batches'],
                'pending_approvals'      => $monitoring['pending_approvals'],
            ],
        ]);
    }

    /**
     * تحديد النطاق الزمني والفترة المقارنة السابقة ونطاق الأقسام الهرمي
     *
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function resolvePeriodAndScope(array $validated): array
    {
        $today = Carbon::today();

        $startDate = ! empty($validated['date_from'])
            ? Carbon::parse((string) $validated['date_from'])->startOfDay()
            : $today->copy()->startOfMonth()->startOfDay();

        $endDate = ! empty($validated['date_to'])
            ? Carbon::parse((string) $validated['date_to'])->endOfDay()
            : $today->copy()->endOfDay();

        if ($endDate->lt($startDate)) {
            $endDate = $startDate->copy()->endOfDay();
        }

        $daysCount = max(1, (int) $startDate->diffInDays($endDate) + 1);
        $prevEndDate = $startDate->copy()->subDay()->endOfDay();
        $prevStartDate = $prevEndDate->copy()->subDays($daysCount - 1)->startOfDay();

        $departmentId = isset($validated['department_id']) ? (int) $validated['department_id'] : null;
        $departmentIds = null;

        if ($departmentId !== null) {
            $department = Department::find($departmentId);
            $departmentIds = $department
                ? $department->descendants()->pluck('id')->push($department->id)->map(fn ($id) => (int) $id)->unique()->values()->all()
                : [$departmentId];
        }

        $baseCurrency = Currency::where('is_base', true)->first() ?? Currency::first();

        return [
            'start_date'      => $startDate,
            'end_date'        => $endDate,
            'prev_start_date' => $prevStartDate,
            'prev_end_date'   => $prevEndDate,
            'department_id'   => $departmentId,
            'department_ids'  => $departmentIds,
            'base_currency'   => $baseCurrency,
        ];
    }

    /**
     * بناء بطاقات المؤشرات الرئيسية الخمسة (KPIs)
     *
     * @param array<int, int>|null $departmentIds
     * @return array<string, mixed>
     */
    private function buildKpis(
        Carbon $startDate,
        Carbon $endDate,
        Carbon $prevStartDate,
        Carbon $prevEndDate,
        ?array $departmentIds
    ): array {
        $todayStr = Carbon::today()->toDateString();

        // 1. القوى العاملة النشطة والعقود السارية
        $activeEmployeesQuery = Employee::query()
            ->whereNotIn('status', [
                EmployeeStatus::Dismissed->value,
                EmployeeStatus::EndOfService->value,
            ]);
        $this->applyEmployeeDepartmentFilter($activeEmployeesQuery, $departmentIds);

        $statusCounts = (clone $activeEmployeesQuery)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $totalActiveEmployees = (int) $statusCounts->sum();
        $inServiceCount = (int) ($statusCounts[EmployeeStatus::InService->value] ?? 0);
        $onLeaveCount = (int) ($statusCounts[EmployeeStatus::Leave->value] ?? 0);

        $activeContractsStats = Contract::query()
            ->join('employees', 'contracts.employee_id', '=', 'employees.id')
            ->whereNull('contracts.deleted_at')
            ->whereNull('employees.deleted_at')
            ->where('employees.employment_type', '!=', EmploymentType::Intern->value)
            ->where('contracts.is_active', true)
            ->where('contracts.start_date', '<=', $todayStr)
            ->where(function ($q) use ($todayStr): void {
                $q->whereNull('contracts.end_date')
                    ->orWhere('contracts.end_date', '>=', $todayStr);
            })
            ->when($departmentIds !== null, fn ($q) => $q->whereIn('employees.department_id', $departmentIds))
            ->selectRaw('COUNT(contracts.id) as cnt, COALESCE(SUM(contracts.basic_salary), 0) as total_basic')
            ->first();

        $activeContractsCount = (int) ($activeContractsStats->cnt ?? 0);
        $activeContractsBasicTotal = (float) ($activeContractsStats->total_basic ?? 0.0);

        // 2. الالتزام المالي لمسيرات الرواتب المعتمدة
        $currentPayrollNet = $this->calculatePostedPayrollNet($startDate, $endDate, $departmentIds);
        $previousPayrollNet = $this->calculatePostedPayrollNet($prevStartDate, $prevEndDate, $departmentIds);

        // 3. انضباط الحضور والعمل الإضافي خلال الفترة
        $attendanceStats = AttendanceLog::query()
            ->join('employees', 'hr_attendance_logs.employee_id', '=', 'employees.id')
            ->whereNull('hr_attendance_logs.deleted_at')
            ->whereNull('employees.deleted_at')
            ->where('employees.employment_type', '!=', EmploymentType::Intern->value)
            ->whereBetween('hr_attendance_logs.date', [$startDate->toDateString(), $endDate->toDateString()])
            ->when($departmentIds !== null, fn ($q) => $q->whereIn('employees.department_id', $departmentIds))
            ->selectRaw("
                SUM(CASE WHEN hr_attendance_logs.status IN ('present', 'late') THEN 1 ELSE 0 END) as attended_days,
                SUM(CASE WHEN hr_attendance_logs.status = 'late' THEN 1 ELSE 0 END) as late_days,
                SUM(CASE WHEN hr_attendance_logs.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                COALESCE(SUM(hr_attendance_logs.overtime_minutes), 0) as total_overtime_minutes,
                COALESCE(SUM(hr_attendance_logs.delay_minutes), 0) as total_delay_minutes
            ")
            ->first();

        $attendedDays = (int) ($attendanceStats->attended_days ?? 0);
        $lateDays = (int) ($attendanceStats->late_days ?? 0);
        $absentDays = (int) ($attendanceStats->absent_days ?? 0);
        $scheduledWorkDays = $attendedDays + $absentDays;
        $complianceRate = $scheduledWorkDays > 0
            ? round(($attendedDays / $scheduledWorkDays) * 100, 1)
            : 100.0;

        $overtimeHours = round(((float) ($attendanceStats->total_overtime_minutes ?? 0)) / 60, 1);
        $delayHours = round(((float) ($attendanceStats->total_delay_minutes ?? 0)) / 60, 1);

        // 4. السلف القائمة والإجازات المعتمدة
        $outstandingLoansStats = LoanInstallment::query()
            ->join('hr_loans', 'hr_loan_installments.loan_id', '=', 'hr_loans.id')
            ->join('employees', 'hr_loans.employee_id', '=', 'employees.id')
            ->whereNull('hr_loan_installments.deleted_at')
            ->whereNull('hr_loans.deleted_at')
            ->whereNull('employees.deleted_at')
            ->where('employees.employment_type', '!=', EmploymentType::Intern->value)
            ->where('hr_loan_installments.status', 'pending')
            ->whereIn('hr_loans.status', ['approved', 'paid_to_employee'])
            ->when($departmentIds !== null, fn ($q) => $q->whereIn('employees.department_id', $departmentIds))
            ->selectRaw('COALESCE(SUM(hr_loan_installments.amount), 0) as pending_balance, COUNT(DISTINCT hr_loans.id) as active_loans')
            ->first();

        $approvedLeavesStats = LeaveRequest::query()
            ->join('employees', 'hr_leave_requests.employee_id', '=', 'employees.id')
            ->whereNull('hr_leave_requests.deleted_at')
            ->whereNull('employees.deleted_at')
            ->where('employees.employment_type', '!=', EmploymentType::Intern->value)
            ->where('hr_leave_requests.status', 'approved')
            ->whereDate('hr_leave_requests.start_date', '<=', $endDate->toDateString())
            ->whereDate('hr_leave_requests.end_date', '>=', $startDate->toDateString())
            ->when($departmentIds !== null, fn ($q) => $q->whereIn('employees.department_id', $departmentIds))
            ->selectRaw('COUNT(hr_leave_requests.id) as requests_count, COALESCE(SUM(hr_leave_requests.total_days), 0) as total_days')
            ->first();

        // 5. التواجد الميداني اللحظي (اليوم) وحركة الزوار
        $checkedInTodayCount = AttendanceLog::query()
            ->join('employees', 'hr_attendance_logs.employee_id', '=', 'employees.id')
            ->whereNull('hr_attendance_logs.deleted_at')
            ->whereNull('employees.deleted_at')
            ->whereDate('hr_attendance_logs.date', $todayStr)
            ->whereNotNull('hr_attendance_logs.check_in')
            ->whereNull('hr_attendance_logs.check_out')
            ->when($departmentIds !== null, fn ($q) => $q->whereIn('employees.department_id', $departmentIds))
            ->count();

        $checkedOutTodayCount = AttendanceLog::query()
            ->join('employees', 'hr_attendance_logs.employee_id', '=', 'employees.id')
            ->whereNull('hr_attendance_logs.deleted_at')
            ->whereNull('employees.deleted_at')
            ->whereDate('hr_attendance_logs.date', $todayStr)
            ->whereNotNull('hr_attendance_logs.check_in')
            ->whereNotNull('hr_attendance_logs.check_out')
            ->when($departmentIds !== null, fn ($q) => $q->whereIn('employees.department_id', $departmentIds))
            ->count();

        $temporaryOutCount = HrLeavePass::query()
            ->join('employees', 'hr_leave_passes.employee_id', '=', 'employees.id')
            ->whereNull('hr_leave_passes.deleted_at')
            ->whereNull('employees.deleted_at')
            ->whereDate('hr_leave_passes.date', $todayStr)
            ->where('hr_leave_passes.status', 'out')
            ->whereNotNull('hr_leave_passes.actual_leave_at')
            ->whereNull('hr_leave_passes.actual_return_at')
            ->when($departmentIds !== null, fn ($q) => $q->whereIn('employees.department_id', $departmentIds))
            ->count();

        $insideDutyCount = max(0, $checkedInTodayCount - $temporaryOutCount);

        $visitorsInsideCount = Visitor::query()
            ->whereDate('visit_date', $todayStr)
            ->where('status', 'checked_in')
            ->when($departmentIds !== null, function (Builder $q) use ($departmentIds): void {
                $q->whereHas('employee', fn (Builder $eq) => $eq->whereIn('department_id', $departmentIds));
            })
            ->count();

        $visitorsExpectedTodayCount = Visitor::query()
            ->whereDate('visit_date', $todayStr)
            ->where('status', 'pending')
            ->when($departmentIds !== null, function (Builder $q) use ($departmentIds): void {
                $q->whereHas('employee', fn (Builder $eq) => $eq->whereIn('department_id', $departmentIds));
            })
            ->count();

        $attendanceSparkline = $this->buildAttendanceSparkline($endDate, $departmentIds);

        return [
            'workforce' => [
                'total_active_employees' => $totalActiveEmployees,
                'in_service_count'       => $inServiceCount,
                'on_leave_count'         => $onLeaveCount,
                'active_contracts_count' => $activeContractsCount,
            ],
            'payroll' => [
                'total_net_salary'             => round($currentPayrollNet, 2),
                'previous_net_salary'          => round($previousPayrollNet, 2),
                'growth_percentage'            => $this->calculateGrowth($currentPayrollNet, $previousPayrollNet),
                'active_contracts_basic_total' => round($activeContractsBasicTotal, 2),
            ],
            'attendance' => [
                'compliance_rate' => $complianceRate,
                'attended_days'   => $attendedDays,
                'late_days'       => $lateDays,
                'absent_days'     => $absentDays,
                'overtime_hours'  => $overtimeHours,
                'delay_hours'     => $delayHours,
                'sparkline'       => $attendanceSparkline,
            ],
            'loans_and_leaves' => [
                'outstanding_loans_balance' => round((float) ($outstandingLoansStats->pending_balance ?? 0.0), 2),
                'active_loans_count'        => (int) ($outstandingLoansStats->active_loans ?? 0),
                'approved_leave_days'       => round((float) ($approvedLeavesStats->total_days ?? 0.0), 1),
                'approved_leaves_count'     => (int) ($approvedLeavesStats->requests_count ?? 0),
            ],
            'live_presence' => [
                'date'                         => $todayStr,
                'inside_duty_count'            => $insideDutyCount,
                'temporary_out_count'          => $temporaryOutCount,
                'checked_out_today_count'      => $checkedOutTodayCount,
                'visitors_inside_count'        => $visitorsInsideCount,
                'visitors_expected_today_count' => $visitorsExpectedTodayCount,
            ],
        ];
    }

    /**
     * احتساب صافي الرواتب المعتمدة خلال فترة مالية محددة
     *
     * @param array<int, int>|null $departmentIds
     */
    private function calculatePostedPayrollNet(Carbon $startDate, Carbon $endDate, ?array $departmentIds): float
    {
        $total = Payslip::query()
            ->join('payroll_batches', 'payslips.payroll_batch_id', '=', 'payroll_batches.id')
            ->join('hr_pay_periods', 'payroll_batches.pay_period_id', '=', 'hr_pay_periods.id')
            ->join('employees', 'payslips.employee_id', '=', 'employees.id')
            ->whereNull('payroll_batches.deleted_at')
            ->whereNull('hr_pay_periods.deleted_at')
            ->whereNull('employees.deleted_at')
            ->where('payroll_batches.status', 'posted')
            ->whereDate('hr_pay_periods.end_date', '>=', $startDate->toDateString())
            ->whereDate('hr_pay_periods.end_date', '<=', $endDate->toDateString())
            ->when($departmentIds !== null, fn ($q) => $q->whereIn('employees.department_id', $departmentIds))
            ->sum('payslips.net_salary');

        return (float) $total;
    }

    /**
     * Chart 1: المنحنى الزمني لتكلفة مسيرات الرواتب المعتمدة (آخر 6 أشهر أو ضمن النطاق)
     *
     * @param array<int, int>|null $departmentIds
     * @return array<string, mixed>
     */
    private function buildPayrollTrendChart(Carbon $startDate, Carbon $endDate, ?array $departmentIds): array
    {
        $arabicMonths = [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
            5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
        ];

        $trendStart = $endDate->copy()->subMonths(5)->startOfMonth();
        if ($startDate->lt($trendStart)) {
            $trendStart = $startDate->copy()->startOfMonth();
        }
        $trendEnd = $endDate->copy()->endOfMonth();

        $buckets = [];
        $period = CarbonPeriod::create($trendStart, '1 month', $trendEnd);

        foreach ($period as $dt) {
            $key = $dt->format('Y-m');
            $buckets[$key] = [
                'label'      => $arabicMonths[(int) $dt->month] . ' ' . $dt->year,
                'basic'      => 0.0,
                'allowances' => 0.0,
                'deductions' => 0.0,
                'net'        => 0.0,
            ];
        }

        $rows = Payslip::query()
            ->join('payroll_batches', 'payslips.payroll_batch_id', '=', 'payroll_batches.id')
            ->join('hr_pay_periods', 'payroll_batches.pay_period_id', '=', 'hr_pay_periods.id')
            ->join('employees', 'payslips.employee_id', '=', 'employees.id')
            ->whereNull('payroll_batches.deleted_at')
            ->whereNull('hr_pay_periods.deleted_at')
            ->whereNull('employees.deleted_at')
            ->where('payroll_batches.status', 'posted')
            ->whereDate('hr_pay_periods.end_date', '>=', $trendStart->toDateString())
            ->whereDate('hr_pay_periods.end_date', '<=', $trendEnd->toDateString())
            ->when($departmentIds !== null, fn ($q) => $q->whereIn('employees.department_id', $departmentIds))
            ->select([
                'hr_pay_periods.end_date',
                'payslips.basic_salary',
                'payslips.total_allowances',
                'payslips.total_deductions',
                'payslips.net_salary',
            ])
            ->get();

        foreach ($rows as $row) {
            $key = Carbon::parse($row->end_date)->format('Y-m');
            if (! isset($buckets[$key])) {
                continue;
            }
            $buckets[$key]['basic'] += (float) $row->basic_salary;
            $buckets[$key]['allowances'] += (float) $row->total_allowances;
            $buckets[$key]['deductions'] += (float) $row->total_deductions;
            $buckets[$key]['net'] += (float) $row->net_salary;
        }

        $labels = [];
        $basicSeries = [];
        $allowancesSeries = [];
        $deductionsSeries = [];
        $netSeries = [];

        foreach ($buckets as $b) {
            $labels[] = $b['label'];
            $basicSeries[] = round($b['basic'], 2);
            $allowancesSeries[] = round($b['allowances'], 2);
            $deductionsSeries[] = round($b['deductions'], 2);
            $netSeries[] = round($b['net'], 2);
        }

        return [
            'labels'            => $labels,
            'basic_series'      => $basicSeries,
            'allowances_series' => $allowancesSeries,
            'deductions_series' => $deductionsSeries,
            'net_series'        => $netSeries,
        ];
    }

    /**
     * Chart 2: توزيع القوى العاملة النشطة وكتلة الرواتب الأساسية حسب الأقسام
     *
     * @param array<int, int>|null $departmentIds
     * @return array<string, mixed>
     */
    private function buildEmployeesByDepartmentChart(?array $departmentIds): array
    {
        $todayStr = Carbon::today()->toDateString();

        $rows = Employee::query()
            ->leftJoin('departments', 'employees.department_id', '=', 'departments.id')
            ->leftJoin('contracts', function ($join) use ($todayStr): void {
                $join->on('employees.id', '=', 'contracts.employee_id')
                    ->whereNull('contracts.deleted_at')
                    ->where('contracts.is_active', true)
                    ->where('contracts.start_date', '<=', $todayStr)
                    ->where(function ($q) use ($todayStr): void {
                        $q->whereNull('contracts.end_date')
                            ->orWhere('contracts.end_date', '>=', $todayStr);
                    });
            })
            ->whereNull('employees.deleted_at')
            ->whereNotIn('employees.status', [
                EmployeeStatus::Dismissed->value,
                EmployeeStatus::EndOfService->value,
            ])
            ->when($departmentIds !== null, fn ($q) => $q->whereIn('employees.department_id', $departmentIds))
            ->selectRaw("
                employees.department_id as department_id,
                COALESCE(departments.name, 'غير محدد القسم') as department_name,
                COUNT(DISTINCT employees.id) as employees_count,
                COALESCE(SUM(contracts.basic_salary), 0) as total_basic_salary
            ")
            ->groupBy('employees.department_id', 'departments.name')
            ->orderByDesc('employees_count')
            ->limit(8)
            ->get();

        $items = $rows->map(function ($row) {
            return [
                'department_id'      => $row->department_id ? (int) $row->department_id : null,
                'department_name'    => $row->department_name,
                'employees_count'    => (int) $row->employees_count,
                'total_basic_salary' => round((float) $row->total_basic_salary, 2),
            ];
        })->values();

        return [
            'labels'        => $items->pluck('department_name')->toArray(),
            'count_series'  => $items->pluck('employees_count')->toArray(),
            'salary_series' => $items->pluck('total_basic_salary')->toArray(),
            'items'         => $items->toArray(),
        ];
    }

    /**
     * Chart 3: المنحنى اليومي لحركة الحضور (حاضر في الموعد، متأخر، غائب)
     *
     * @param array<int, int>|null $departmentIds
     * @return array<string, mixed>
     */
    private function buildAttendanceTrendChart(Carbon $startDate, Carbon $endDate, ?array $departmentIds): array
    {
        $chartStart = $startDate->copy()->startOfDay();
        if ($chartStart->diffInDays($endDate) > 31) {
            $chartStart = $endDate->copy()->subDays(30)->startOfDay();
        }

        $buckets = [];
        $period = CarbonPeriod::create($chartStart, '1 day', $endDate->copy()->startOfDay());

        foreach ($period as $dt) {
            $k = $dt->format('Y-m-d');
            $buckets[$k] = [
                'label'   => $dt->format('m-d'),
                'present' => 0,
                'late'    => 0,
                'absent'  => 0,
            ];
        }

        $rows = AttendanceLog::query()
            ->join('employees', 'hr_attendance_logs.employee_id', '=', 'employees.id')
            ->whereNull('hr_attendance_logs.deleted_at')
            ->whereNull('employees.deleted_at')
            ->where('employees.employment_type', '!=', EmploymentType::Intern->value)
            ->whereBetween('hr_attendance_logs.date', [$chartStart->toDateString(), $endDate->toDateString()])
            ->whereIn('hr_attendance_logs.status', ['present', 'late', 'absent'])
            ->when($departmentIds !== null, fn ($q) => $q->whereIn('employees.department_id', $departmentIds))
            ->selectRaw('hr_attendance_logs.date, hr_attendance_logs.status, COUNT(*) as cnt')
            ->groupBy('hr_attendance_logs.date', 'hr_attendance_logs.status')
            ->get();

        foreach ($rows as $row) {
            $k = Carbon::parse($row->date)->format('Y-m-d');
            $st = (string) $row->status;
            if (isset($buckets[$k][$st])) {
                $buckets[$k][$st] = (int) $row->cnt;
            }
        }

        $labels = [];
        $presentSeries = [];
        $lateSeries = [];
        $absentSeries = [];

        foreach ($buckets as $b) {
            $labels[] = $b['label'];
            $presentSeries[] = $b['present'];
            $lateSeries[] = $b['late'];
            $absentSeries[] = $b['absent'];
        }

        return [
            'labels'         => $labels,
            'present_series' => $presentSeries,
            'late_series'    => $lateSeries,
            'absent_series'  => $absentSeries,
        ];
    }

    /**
     * Chart 4: توزيع القوى العاملة حسب الحالة الوظيفية
     *
     * @param array<int, int>|null $departmentIds
     * @return array<string, mixed>
     */
    private function buildEmployeesByStatusChart(?array $departmentIds): array
    {
        $query = Employee::query();
        $this->applyEmployeeDepartmentFilter($query, $departmentIds);

        $rows = $query->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $items = [];
        foreach (EmployeeStatus::cases() as $case) {
            $count = (int) ($rows[$case->value] ?? 0);
            if ($count > 0 || in_array($case, [EmployeeStatus::InService, EmployeeStatus::Leave, EmployeeStatus::Training], true)) {
                $items[] = [
                    'status' => $case->value,
                    'label'  => $case->label(),
                    'count'  => $count,
                ];
            }
        }

        return [
            'labels' => array_column($items, 'label'),
            'series' => array_column($items, 'count'),
            'items'  => $items,
        ];
    }

    /**
     * بناء التنبيهات الإدارية والرقابية والقوائم التشغيلية السريعة
     *
     * @param array<int, int>|null $departmentIds
     * @return array<string, mixed>
     */
    private function buildAlertsAndLists(Carbon $startDate, Carbon $endDate, ?array $departmentIds): array
    {
        $today = Carbon::today();
        $todayStr = $today->toDateString();
        $in30DaysStr = $today->copy()->addDays(30)->toDateString();

        // 1. طلبات الإجازة المعلقة
        $pendingLeavesQuery = LeaveRequest::query()
            ->with(['employee:id,full_name,department_id', 'employee.department:id,name', 'leaveType:id,name'])
            ->where('status', 'pending')
            ->when($departmentIds !== null, function (Builder $q) use ($departmentIds): void {
                $q->whereHas('employee', fn (Builder $eq) => $eq->whereIn('department_id', $departmentIds));
            });

        $pendingLeavesCount = (clone $pendingLeavesQuery)->count();

        // 2. طلبات السلف المعلقة
        $pendingLoansQuery = Loan::query()
            ->with(['employee:id,full_name,department_id', 'employee.department:id,name'])
            ->where('status', 'pending')
            ->when($departmentIds !== null, function (Builder $q) use ($departmentIds): void {
                $q->whereHas('employee', fn (Builder $eq) => $eq->whereIn('department_id', $departmentIds));
            });

        $pendingLoansCount = (clone $pendingLoansQuery)->count();

        // 3. العقود التي ستنتهي خلال 30 يوماً القادمة
        $expiringContractsCount = Contract::query()
            ->join('employees', 'contracts.employee_id', '=', 'employees.id')
            ->whereNull('contracts.deleted_at')
            ->whereNull('employees.deleted_at')
            ->where('employees.employment_type', '!=', EmploymentType::Intern->value)
            ->where('contracts.is_active', true)
            ->whereNotNull('contracts.end_date')
            ->whereBetween('contracts.end_date', [$todayStr, $in30DaysStr])
            ->when($departmentIds !== null, fn ($q) => $q->whereIn('employees.department_id', $departmentIds))
            ->count();

        // 4. طلبات التدريب المعلقة والمتدربون المنتهية فترتهم
        $pendingInternAppsCount = InternshipApplication::where('status', 'pending')->count();
        $completedInternsCount = Employee::withoutGlobalScope('exclude_interns')
            ->where('employment_type', EmploymentType::Intern->value)
            ->where(function (Builder $q) use ($todayStr): void {
                $q->where('internship_status', 'completed')
                    ->orWhere(function (Builder $sub) use ($todayStr): void {
                        $sub->where('internship_status', 'active')
                            ->whereNotNull('internship_end_date')
                            ->whereDate('internship_end_date', '<', $todayStr);
                    });
            })
            ->when($departmentIds !== null, fn (Builder $q) => $q->whereIn('department_id', $departmentIds))
            ->count();

        // 5. التعديلات اليدوية على سجلات الحضور للرقابة الإدارية
        $manualOverridesCount = AttendanceLog::query()
            ->join('employees', 'hr_attendance_logs.employee_id', '=', 'employees.id')
            ->whereNull('hr_attendance_logs.deleted_at')
            ->whereNull('employees.deleted_at')
            ->where('hr_attendance_logs.is_manual_override', true)
            ->whereBetween('hr_attendance_logs.date', [$startDate->toDateString(), $endDate->toDateString()])
            ->when($departmentIds !== null, fn ($q) => $q->whereIn('employees.department_id', $departmentIds))
            ->count();

        // 6. أحدث 5 مسيرات رواتب معتمدة (Posted)
        $recentBatches = PayrollBatch::query()
            ->with(['payPeriod:id,name,start_date,end_date'])
            ->where('status', 'posted')
            ->orderByDesc('approved_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $batchIds = $recentBatches->pluck('id')->all();
        $batchTotalsMap = [];

        if (! empty($batchIds)) {
            $batchTotalsMap = Payslip::query()
                ->join('employees', 'payslips.employee_id', '=', 'employees.id')
                ->whereIn('payslips.payroll_batch_id', $batchIds)
                ->when($departmentIds !== null, fn ($q) => $q->whereIn('employees.department_id', $departmentIds))
                ->selectRaw('
                    payslips.payroll_batch_id,
                    COUNT(payslips.id) as employees_count,
                    COALESCE(SUM(payslips.basic_salary), 0) as total_basic,
                    COALESCE(SUM(payslips.total_allowances), 0) as total_allowances,
                    COALESCE(SUM(payslips.total_deductions), 0) as total_deductions,
                    COALESCE(SUM(payslips.net_salary), 0) as total_net
                ')
                ->groupBy('payslips.payroll_batch_id')
                ->get()
                ->keyBy('payroll_batch_id');
        }

        $recentPayrollBatchesList = $recentBatches->map(function (PayrollBatch $batch) use ($batchTotalsMap) {
            $totals = $batchTotalsMap[$batch->id] ?? null;
            $runTypeEnum = PayrollRunType::tryFrom((string) $batch->run_type);

            return [
                'id'               => $batch->id,
                'number'           => $batch->number,
                'name'             => $batch->name,
                'period_name'      => $batch->payPeriod?->name,
                'run_type'         => $batch->run_type,
                'run_type_label'   => $runTypeEnum ? $runTypeEnum->label() : $batch->run_type,
                'employees_count'  => (int) ($totals->employees_count ?? 0),
                'total_basic'      => round((float) ($totals->total_basic ?? 0.0), 2),
                'total_allowances' => round((float) ($totals->total_allowances ?? 0.0), 2),
                'total_deductions' => round((float) ($totals->total_deductions ?? 0.0), 2),
                'total_net'        => round((float) ($totals->total_net ?? 0.0), 2),
                'approved_at'      => $batch->approved_at?->format('Y-m-d H:i'),
            ];
        })->values()->toArray();

        // 7. أحدث الطلبات المعلقة بانتظار الاعتماد (إجازات + سلف)
        $latestPendingLeaves = $pendingLeavesQuery->latest('id')->limit(4)->get()->map(function (LeaveRequest $req) {
            return [
                'type'            => 'leave',
                'type_label'      => 'طلب إجازة',
                'id'              => $req->id,
                'employee_name'   => $req->employee?->full_name,
                'department_name' => $req->employee?->department?->name,
                'title'           => $req->leaveType?->name ?? 'إجازة',
                'value'           => (float) $req->total_days,
                'unit'            => 'يوم',
                'target_date'     => $req->start_date?->format('Y-m-d'),
                'created_at'      => $req->created_at?->format('Y-m-d H:i'),
                'sort_ts'         => $req->created_at?->timestamp ?? 0,
            ];
        });

        $latestPendingLoans = $pendingLoansQuery->latest('id')->limit(4)->get()->map(function (Loan $loan) {
            return [
                'type'            => 'loan',
                'type_label'      => 'طلب سلفة',
                'id'              => $loan->id,
                'employee_name'   => $loan->employee?->full_name,
                'department_name' => $loan->employee?->department?->name,
                'title'           => $loan->reason ?: 'سلفة مالية',
                'value'           => round((float) $loan->amount, 2),
                'unit'            => 'مبلغ',
                'target_date'     => $loan->deduction_start_date?->format('Y-m-d'),
                'created_at'      => $loan->created_at?->format('Y-m-d H:i'),
                'sort_ts'         => $loan->created_at?->timestamp ?? 0,
            ];
        });

        $pendingApprovals = $latestPendingLeaves
            ->concat($latestPendingLoans)
            ->sortByDesc('sort_ts')
            ->take(6)
            ->map(function (array $item): array {
                unset($item['sort_ts']);
                return $item;
            })
            ->values()
            ->toArray();

        return [
            'alerts' => [
                'pending_leave_requests_count'      => $pendingLeavesCount,
                'pending_loans_count'               => $pendingLoansCount,
                'expiring_contracts_count'          => $expiringContractsCount,
                'pending_intern_applications_count' => $pendingInternAppsCount,
                'completed_interns_count'           => $completedInternsCount,
                'manual_attendance_overrides_count' => $manualOverridesCount,
            ],
            'recent_payroll_batches' => $recentPayrollBatchesList,
            'pending_approvals'      => $pendingApprovals,
        ];
    }

    /**
     * بناء نقاط Sparkline اليومية لنسبة الانضباط لآخر 7 أيام
     *
     * @param array<int, int>|null $departmentIds
     * @return array<int, float>
     */
    private function buildAttendanceSparkline(Carbon $endDate, ?array $departmentIds): array
    {
        $start7D = $endDate->copy()->subDays(6)->startOfDay();
        $trend = $this->buildAttendanceTrendChart($start7D, $endDate, $departmentIds);

        $sparkline = [];
        $count = count($trend['labels']);

        for ($i = 0; $i < $count; $i++) {
            $attended = $trend['present_series'][$i] + $trend['late_series'][$i];
            $total = $attended + $trend['absent_series'][$i];
            $sparkline[] = $total > 0 ? round(($attended / $total) * 100, 1) : 100.0;
        }

        return $sparkline;
    }

    /**
     * تطبيق فلتر القسم (ومشتقاته الهرمية) على استعلام الموظفين
     *
     * @param Builder $query
     * @param array<int, int>|null $departmentIds
     */
    private function applyEmployeeDepartmentFilter(Builder $query, ?array $departmentIds): void
    {
        if ($departmentIds !== null) {
            $query->whereIn('department_id', $departmentIds);
        }
    }

    /**
     * حساب نسبة النمو المئوية مقارنة بالفترة السابقة
     */
    private function calculateGrowth(float $current, float $previous): float
    {
        if (abs($previous) < 0.0001) {
            return abs($current) < 0.0001 ? 0.0 : 100.0;
        }

        return round((($current - $previous) / abs($previous)) * 100, 1);
    }
}