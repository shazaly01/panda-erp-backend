<?php

declare(strict_types=1);

namespace App\Modules\HR\Services;

use App\Modules\HR\Enums\EmployeeStatus;
use App\Modules\HR\Enums\PayrollRunType;
use App\Modules\HR\Enums\SalaryRuleCategory;
use App\Modules\HR\Enums\SalaryRuleType;
use App\Modules\HR\Models\AttendanceLog;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\LeaveRequest;
use App\Modules\HR\Models\LoanInstallment;
use App\Modules\HR\Models\OvertimePolicy;
use App\Modules\HR\Models\PayPeriod;
use App\Modules\HR\Models\PayrollInput;
use App\Modules\HR\Models\Payslip;
use App\Modules\HR\Models\SalaryRule;
use BackedEnum;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Throwable;

class PayrollService
{
    public function __construct(
        protected TimeEvaluationService $timeEvaluation
    ) {}

    /**
     * تجميع المدخلات المتغيرة للموظف في فترة معينة (آلياً) مع تفاصيل الشفافية الكاملة
     * تشمل: أقساط السلف المصروفة والمستحقة، الحوافز والبدلات والاستقطاعات اليدوية بأسبابها، الإجازات غير المدفوعة، والغياب والتأخير.
     */
    protected function gatherAutomatedInputs(
        Employee $employee,
        string $startDate,
        string $endDate,
        SupportCollection $rules,
        ?Collection $preloadedAttendanceLogs = null,
        bool $isOvertimeOnly = false,
        ?int $editingBatchId = null
    ): array {
        if ($isOvertimeOnly) {
            return [
                '_INPUT_ITEMS'         => [],
                '_LOAN_ITEMS'          => [],
                '_ABSENT_DATES'        => [],
                '_UNPAID_LEAVE_RANGES' => [],
            ];
        }

        $inputs = [];

        // 1. جلب أقساط السلف المستحقة في هذه الفترة (يشمل الأقساط المرتبطة بالمسير الجاري تعديله إن وجد)
        $installments = LoanInstallment::whereHas('loan', function ($q) use ($employee): void {
                $q->where('employee_id', $employee->id)
                  ->where('status', 'paid_to_employee');
            })
            ->where(function ($q) use ($editingBatchId): void {
                $q->where('status', 'pending');
                if ($editingBatchId !== null) {
                    $q->orWhere(function ($sub) use ($editingBatchId): void {
                        $sub->where('status', 'deducted')
                            ->where('payroll_batch_id', $editingBatchId);
                    });
                }
            })
            ->whereBetween('due_month', [$startDate, $endDate])
            ->orderBy('due_month')
            ->get();

        $totalLoanDeduction = (float) $installments->sum('amount');
        if ($totalLoanDeduction > 0) {
            $inputs['LOAN_DEDUCTION'] = round($totalLoanDeduction, 2);
        }

        $loanItems = [];
        foreach ($installments as $inst) {
            $remainingQuery = LoanInstallment::where('loan_id', $inst->loan_id)
                ->where('status', 'pending')
                ->where('id', '!=', $inst->id);

            $loanItems[] = [
                'id'               => $inst->id,
                'loan_id'          => $inst->loan_id,
                'amount'           => round((float) $inst->amount, 2),
                'due_month'        => $inst->due_month?->format('Y-m-d'),
                'remaining_amount' => round((float) (clone $remainingQuery)->sum('amount'), 2),
                'remaining_count'  => (int) (clone $remainingQuery)->count(),
            ];
        }
        $inputs['_LOAN_ITEMS'] = $loanItems;

        // 2. جلب كافة المدخلات المتغيرة غير المعالجة (أو المرتبطة بالمسير الجاري تعديله)
        $payrollInputs = PayrollInput::where('employee_id', $employee->id)
            ->where(function ($q) use ($editingBatchId): void {
                $q->where('is_processed', false);
                if ($editingBatchId !== null) {
                    $q->orWhere('payroll_batch_id', $editingBatchId);
                }
            })
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $inputs['_INPUT_ITEMS'] = $payrollInputs->map(fn (PayrollInput $item): array => [
            'id'     => $item->id,
            'type'   => $item->type,
            'amount' => round((float) $item->amount, 2),
            'date'   => $item->date?->format('Y-m-d'),
            'reason' => $item->reason,
        ])->values()->all();

        $totalBonus = (float) $payrollInputs->where('type', 'bonus')->sum('amount');
        $totalVariableAllowance = (float) $payrollInputs->where('type', 'allowance')->sum('amount');
        $totalPenalty = (float) $payrollInputs->where('type', 'penalty')->sum('amount');
        $totalVariableDeduction = (float) $payrollInputs->where('type', 'deduction')->sum('amount');

        $ruleCodes = $rules->pluck('code')->all();

        // دعم فصل البدلات المتغيرة عن المكافآت إذا وُجدت قاعدة مستقلة، أو دمجهما في BONUS لمنع تجاهل أي مبلغ
        if (in_array('VARIABLE_ALLOWANCE', $ruleCodes, true) || in_array('ALLOWANCE', $ruleCodes, true)) {
            if ($totalBonus > 0) {
                $inputs['BONUS'] = round($totalBonus, 2);
            }
            if ($totalVariableAllowance > 0) {
                $inputs['VARIABLE_ALLOWANCE'] = round($totalVariableAllowance, 2);
                $inputs['ALLOWANCE'] = round($totalVariableAllowance, 2);
            }
        } else {
            $combinedAdditions = $totalBonus + $totalVariableAllowance;
            if ($combinedAdditions > 0) {
                $inputs['BONUS'] = round($combinedAdditions, 2);
            }
        }

        // دعم فصل الاستقطاعات المتغيرة عن الجزاءات إذا وُجدت قاعدة مستقلة، أو دمجهما في PENALTY لمنع تجاهل أي مبلغ
        if (in_array('VARIABLE_DEDUCTION', $ruleCodes, true) || in_array('DEDUCTION', $ruleCodes, true)) {
            if ($totalPenalty > 0) {
                $inputs['PENALTY'] = round($totalPenalty, 2);
            }
            if ($totalVariableDeduction > 0) {
                $inputs['VARIABLE_DEDUCTION'] = round($totalVariableDeduction, 2);
                $inputs['DEDUCTION'] = round($totalVariableDeduction, 2);
            }
        } else {
            $combinedDeductions = $totalPenalty + $totalVariableDeduction;
            if ($combinedDeductions > 0) {
                $inputs['PENALTY'] = round($combinedDeductions, 2);
            }
        }

        // 3. احتساب أيام الإجازات غير المدفوعة المعتمدة (is_paid = false) بتقاطعها الدقيق مع الفترة المالية
        $periodStartCarbon = Carbon::parse($startDate)->startOfDay();
        $periodEndCarbon = Carbon::parse($endDate)->startOfDay();
        $unpaidLeaveDays = 0.0;
        $unpaidLeaveRanges = [];

        $unpaidLeaves = LeaveRequest::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->whereHas('leaveType', function ($q): void {
                $q->where('is_paid', false);
            })
            ->get();

        foreach ($unpaidLeaves as $leave) {
            $leaveStart = Carbon::parse($leave->start_date)->startOfDay();
            $leaveEnd = Carbon::parse($leave->end_date)->startOfDay();
            $daysInPeriod = 0.0;

            if ($leaveStart->gte($periodStartCarbon) && $leaveEnd->lte($periodEndCarbon) && (float) $leave->total_days > 0) {
                $daysInPeriod = (float) $leave->total_days;
            } else {
                $overlapStart = $leaveStart->gt($periodStartCarbon) ? $leaveStart : $periodStartCarbon;
                $overlapEnd = $leaveEnd->lt($periodEndCarbon) ? $leaveEnd : $periodEndCarbon;
                if ($overlapEnd->gte($overlapStart)) {
                    $daysInPeriod = (float) ($overlapStart->diffInDays($overlapEnd) + 1);
                }
            }

            $unpaidLeaveDays += $daysInPeriod;
            if ($daysInPeriod > 0) {
                $unpaidLeaveRanges[] = [
                    'start_date' => $leaveStart->format('Y-m-d'),
                    'end_date'   => $leaveEnd->format('Y-m-d'),
                    'days'       => round($daysInPeriod, 2),
                ];
            }
        }

        $inputs['UNPAID_LEAVE_DAYS'] = round($unpaidLeaveDays, 2);
        $inputs['_UNPAID_LEAVE_RANGES'] = $unpaidLeaveRanges;

        // 4. جلب التأخيرات والانصراف المبكر والغياب (مع استبعاد أيام الإجازة غير المدفوعة لمنع ازدواجية الخصم)
        $attendanceLogs = $preloadedAttendanceLogs ?? AttendanceLog::where('employee_id', $employee->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        $absentLogs = $attendanceLogs->where('status', 'absent');
        if ($unpaidLeaves->isNotEmpty()) {
            $absentLogs = $absentLogs->reject(function (AttendanceLog $log) use ($unpaidLeaves): bool {
                $logDate = Carbon::parse($log->date)->startOfDay();
                foreach ($unpaidLeaves as $leave) {
                    $lStart = Carbon::parse($leave->start_date)->startOfDay();
                    $lEnd = Carbon::parse($leave->end_date)->startOfDay();
                    if ($logDate->betweenIncluded($lStart, $lEnd)) {
                        return true;
                    }
                }
                return false;
            });
        }

        $inputs['DELAY_MINUTES'] = (int) $attendanceLogs->sum('delay_minutes');
        $inputs['EARLY_LEAVE_MINUTES'] = (int) $attendanceLogs->sum('early_leave_minutes');
        $inputs['ABSENT_DAYS'] = (int) $absentLogs->count();
        $inputs['_ABSENT_DATES'] = $absentLogs
            ->map(fn (AttendanceLog $log): string => Carbon::parse($log->date)->format('Y-m-d'))
            ->sort()
            ->values()
            ->all();

        return $inputs;
    }

    /**
     * حساب قسيمة راتب افتراضية (Preview) لموظف معين لفترة محددة مع كامل تفاصيل الشفافية
     */
    public function previewPayslip(Employee $employee, PayPeriod $period, PayrollRunType $runType, ?int $editingBatchId = null): array
    {
        // 1. جلب العقد النشط والساري المتوافق مع تواريخ الفترة ومجموعة الدفع
        $contract = $employee->contractForPeriod($period);

        if (!$contract) {
            throw new Exception("الموظف ({$employee->full_name}) ليس لديه عقد عمل نشط وسارٍ متوافق مع هذه الفترة المالية!");
        }

        // 2. التأكد من وجود هيكل رواتب مرتبط بالعقد وقواعده النشطة
        $salaryStructure = $contract->salaryStructure;
        if (!$salaryStructure) {
            throw new Exception("عقد الموظف ({$employee->full_name}) غير مربوط بهيكل رواتب معتمد!");
        }

        $rules = $salaryStructure->rules;
        if ($rules->isEmpty()) {
            throw new Exception("هيكل الرواتب ({$salaryStructure->name}) لا يحتوي على أي قواعد مالية نشطة!");
        }

        // استخراج تواريخ الفترة المالية
        $startDate = $period->start_date->format('Y-m-d');
        $endDate = $period->end_date->format('Y-m-d');
        $isOvertimeOnly = ($runType === PayrollRunType::OvertimeOnly);

        // 3. جلب سياسة الأوفرتايم المعتمدة أو تطبيق المعايير الافتراضية
        $policy = $contract->overtimePolicy ?? new OvertimePolicy([
            'working_days_per_month'      => 30,
            'working_hours_per_day'       => 8,
            'regular_rate'                => 1.5,
            'weekend_rate'                => 2.0,
            'holiday_rate'                => 2.0,
            'is_daily_basis'              => false,
            'wage_factor'                 => 1.0000,
            'fixed_monthly_hours'         => null,
            'tax_rate'                    => 0.00,
            'tax_exempt_employment_types' => ['daily', 'temporary', 'يومية', 'مؤقت'],
        ]);

        // 4. تحميل سجلات الحضور مرة واحدة وتمريرها لمدخلات الخصم ومحرك الساعات
        $attendanceLogs = AttendanceLog::where('employee_id', $employee->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        $automatedInputs = $this->gatherAutomatedInputs(
            $employee,
            $startDate,
            $endDate,
            $rules,
            $attendanceLogs,
            $isOvertimeOnly,
            $editingBatchId
        );

        $timeEvaluations = $this->timeEvaluation->evaluatePeriod(
            $employee,
            $startDate,
            $endDate,
            $policy,
            $attendanceLogs
        );

        // 5. حساب أيام الفترة وأيام سريان العقد الفعلية (لمعالجة التعيين أو الإنهاء في منتصف الشهر)
        $periodStart = Carbon::parse($startDate)->startOfDay();
        $periodEnd = Carbon::parse($endDate)->startOfDay();
        $periodDays = (int) $periodStart->diffInDays($periodEnd) + 1;

        $contractStart = $contract->start_date ? Carbon::parse($contract->start_date)->startOfDay() : $periodStart;
        $contractEnd = $contract->end_date ? Carbon::parse($contract->end_date)->startOfDay() : $periodEnd;

        $effectiveStart = $contractStart->gt($periodStart) ? $contractStart : $periodStart;
        $effectiveEnd = $contractEnd->lt($periodEnd) ? $contractEnd : $periodEnd;
        $effectiveDays = $effectiveEnd->gte($effectiveStart)
            ? (int) $effectiveStart->diffInDays($effectiveEnd) + 1
            : 0;

        $absentDays = (int) ($automatedInputs['ABSENT_DAYS'] ?? 0);
        $unpaidLeaveDays = (float) ($automatedInputs['UNPAID_LEAVE_DAYS'] ?? 0.0);
        $workedDays = max(0, $effectiveDays - $absentDays - $unpaidLeaveDays);

        // 6. الحساب الديناميكي للراتب الأساسي وأجر الساعة وفق سياسة المنشأة
        $basicSalary = (float) $contract->basic_salary;
        $proratedBasic = ($periodDays > 0 && $effectiveDays < $periodDays)
            ? round($basicSalary * ($effectiveDays / $periodDays), 2)
            : $basicSalary;

        $wageFactor = (float) ($policy->wage_factor > 0 ? $policy->wage_factor : 1.0000);
        $workingDaysPerMonth = (int) ($policy->working_days_per_month > 0 ? $policy->working_days_per_month : 30);
        $workingHoursPerDay = (float) ($policy->working_hours_per_day > 0 ? $policy->working_hours_per_day : 8.0);

        if (!empty($policy->fixed_monthly_hours) && (int) $policy->fixed_monthly_hours > 0) {
            $standardMonthlyHours = (float) $policy->fixed_monthly_hours;
        } else {
            $standardMonthlyHours = (float) ($workingDaysPerMonth * $workingHoursPerDay);
        }

        if ($standardMonthlyHours <= 0) {
            $standardMonthlyHours = 176.0;
        }

        $normalHourRate = ($basicSalary * $wageFactor) / $standardMonthlyHours;

        $holidayRate = (float) ($policy->holiday_rate > 0 ? $policy->holiday_rate : 2.0);
        $weekendRate = (float) ($policy->weekend_rate > 0 ? $policy->weekend_rate : 2.0);
        $regularRate = (float) ($policy->regular_rate > 0 ? $policy->regular_rate : 1.5);

        // استخراج ساعات وأيام العمل الإضافي المنجزة
        $otRegularHours = (float) ($timeEvaluations['OT_REGULAR_HOURS'] ?? 0.0);
        $otRegularDays = (int) ($timeEvaluations['OT_REGULAR_DAYS'] ?? 0);
        $otWeekendHours = (float) ($timeEvaluations['OT_WEEKEND_HOURS'] ?? 0.0);
        $otHolidayHours = (float) ($timeEvaluations['OT_HOLIDAY_HOURS'] ?? 0.0);

        // حساب الاستحقاق الإضافي
        $holidayEarnings = $otHolidayHours * $normalHourRate * $holidayRate;
        $weekendEarnings = $otWeekendHours * $normalHourRate * $weekendRate;
        $regularHourlyEarnings = $otRegularHours * $normalHourRate * $regularRate;
        $regularDailyEarnings = $otRegularDays * ($workingHoursPerDay * $normalHourRate * $regularRate);
        $regularEarnings = $regularHourlyEarnings + $regularDailyEarnings;

        $grossOvertime = $holidayEarnings + $weekendEarnings + $regularEarnings;

        // التحقق الديناميكي من الفئات المعفاة من الضريبة
        $exemptTypes = is_array($policy->tax_exempt_employment_types) && !empty($policy->tax_exempt_employment_types)
            ? array_map(static fn ($v) => mb_strtolower(trim((string) $v)), $policy->tax_exempt_employment_types)
            : ['daily', 'temporary', 'يومية', 'مؤقت'];

        $rawEmpType = $employee->employment_type instanceof BackedEnum
            ? (string) $employee->employment_type->value
            : (string) ($employee->employment_type ?? '');
        $empType = mb_strtolower(trim($rawEmpType));
        $isDailyWorker = in_array($empType, $exemptTypes, true);

        $configuredTaxRate = (float) ($policy->tax_rate ?? 0.0);
        $effectiveTaxRate = ($isDailyWorker || $configuredTaxRate <= 0) ? 0.0 : ($configuredTaxRate / 100.0);

        $overtimeTax = round($grossOvertime * $effectiveTaxRate, 2);
        $netOvertime = round($grossOvertime - $overtimeTax, 2);

        // 7. تهيئة ذاكرة السياق الحسابي (Context) بكافة القيم الديناميكية وتفاصيل الشفافية
        $context = array_merge([
            'BASIC'                => $isOvertimeOnly ? 0.0 : $proratedBasic,
            'CONTRACT_BASIC'       => $basicSalary,
            'WAGE_FACTOR'          => $wageFactor,
            'STANDARD_HOURS'       => $standardMonthlyHours,
            'WORKING_DAYS'         => $workingDaysPerMonth,
            'WORKING_HOURS'        => $workingHoursPerDay,
            'NORMAL_HOUR_RATE'     => round($normalHourRate, 4),
            'HOLIDAY_HOUR_RATE'    => round($normalHourRate * $holidayRate, 4),
            'REGULAR_OT_HOUR_RATE' => round($normalHourRate * $regularRate, 4),
            'OT_HOL_HOURS'         => $otHolidayHours,
            'OT_REG_HOURS'         => $otRegularHours,
            'OT_WKD_HOURS'         => $otWeekendHours,
            'OT_HOLIDAY_DAYS'      => (int) ($timeEvaluations['OT_HOLIDAY_DAYS'] ?? 0),
            'PERIOD_DAYS'          => $periodDays,
            'EFFECTIVE_DAYS'       => $effectiveDays,
            'WORKED_DAYS'          => $workedDays,
            'OVERTIME_GROSS'       => round($grossOvertime, 2),
            'OVERTIME_TAX'         => $overtimeTax,
            'NET_OVERTIME'         => $netOvertime,
            'TAX_RATE'             => $configuredTaxRate,
            'IS_DAILY_WORKER'      => $isDailyWorker ? 1 : 0,
            'OVERTIME_AMOUNT'      => round($grossOvertime, 2),
        ], $automatedInputs, $timeEvaluations);

        $lines = [];
        $totalAllowances = 0.0;
        $totalDeductions = 0.0;
        $totalCompanyContributions = 0.0;

        // 8. تطبيق قواعد هيكل الراتب بالترتيب التسلسلي مع تقريب كل بند لضمان توازن القيد المحاسبي
        foreach ($rules as $rule) {
            $amount = 0.0;
            $code = $rule->code;

            // في مسير الإضافي فقط، يتم تخطي كل القواعد باستثناء قاعدة الإضافي والضريبة
            if ($isOvertimeOnly && !in_array($code, ['OVERTIME_AMOUNT', 'OVERTIME_TAX'], true)) {
                continue;
            }

            if ($rule->type === SalaryRuleType::Fixed) {
                $amount = ($code === 'BASIC') ? (float) $context['BASIC'] : (float) $rule->value;
            } elseif ($rule->type === SalaryRuleType::Percentage) {
                $baseValue = (float) ($context[$rule->percentage_of_code] ?? 0.0);
                $amount = $baseValue * ((float) $rule->value / 100);
            } elseif ($rule->type === SalaryRuleType::Formula) {
                $amount = $this->evaluateFormula($rule->formula_expression, $context, $code);
            } elseif ($rule->type === SalaryRuleType::Input) {
                $amount = (float) ($context[$code] ?? 0.0);
            }

            $roundedAmount = round((float) $amount, 2);
            $context[$code] = $roundedAmount;

            if ($roundedAmount > 0) {
                if ($rule->category === SalaryRuleCategory::Allowance) {
                    $totalAllowances = round($totalAllowances + $roundedAmount, 2);
                } elseif ($rule->category === SalaryRuleCategory::Deduction) {
                    $totalDeductions = round($totalDeductions + $roundedAmount, 2);
                } elseif ($rule->category === SalaryRuleCategory::CompanyContribution) {
                    $totalCompanyContributions = round($totalCompanyContributions + $roundedAmount, 2);
                }

                $lines[] = [
                    'code'     => $code,
                    'name'     => $rule->name,
                    'category' => $rule->category->value,
                    'amount'   => $roundedAmount,
                ];
            }
        }

        // إدراج بند استقطاع ضريبة الإضافي تلقائياً فقط إذا وُجدت ضريبة مستحقة وكانت القاعدة مهيأة محاسبياً
        if ($overtimeTax > 0 && !collect($lines)->contains('code', 'OVERTIME_TAX')) {
            $taxRule = SalaryRule::where('code', 'OVERTIME_TAX')
                ->where('is_active', true)
                ->first();

            if (!$taxRule || !$taxRule->account_mapping_key) {
                throw new Exception("يوجد استقطاع ضريبة إضافي مستحق ({$overtimeTax})، ولكن قاعدة الراتب (OVERTIME_TAX) غير معرفة أو غير مربوطة محاسبياً!");
            }

            $roundedTax = round($overtimeTax, 2);
            $totalDeductions = round($totalDeductions + $roundedTax, 2);
            $lines[] = [
                'code'     => 'OVERTIME_TAX',
                'name'     => $taxRule->name ?: "ضريبة كسب العمل الإضافي ({$configuredTaxRate}%)",
                'category' => SalaryRuleCategory::Deduction->value,
                'amount'   => $roundedTax,
            ];
        }

        $netSalary = round($totalAllowances - $totalDeductions, 2);

        // جلب بيانات الصرف والمديونية المتراكمة للموظف لتعزيز الشفافية
        $existingPayslip = $editingBatchId !== null
            ? Payslip::with('batch:id,disbursement_percentage')
                ->where('payroll_batch_id', $editingBatchId)
                ->where('employee_id', $employee->id)
                ->first()
            : Payslip::with('batch:id,disbursement_percentage')
                ->where('employee_id', $employee->id)
                ->whereHas('batch', function ($q) use ($period, $runType): void {
                    $q->where('status', 'posted')
                      ->where('pay_period_id', $period->id)
                      ->where('run_type', $runType->value);
                })
                ->first();

        $previousUnpaidBalance = (float) Payslip::where('employee_id', $employee->id)
            ->when($existingPayslip, fn ($q) => $q->where('id', '!=', $existingPayslip->id))
            ->whereHas('batch', fn ($q) => $q->where('status', 'posted'))
            ->sum('remaining_amount');

        $disbursementInfo = [
            'disbursement_percentage'   => $existingPayslip?->batch?->disbursement_percentage !== null
                ? (float) $existingPayslip->batch->disbursement_percentage
                : 100.00,
            'paid_amount'               => $existingPayslip ? (float) $existingPayslip->paid_amount : $netSalary,
            'remaining_amount'          => $existingPayslip ? (float) $existingPayslip->remaining_amount : 0.00,
            'previous_unpaid_balance'   => round($previousUnpaidBalance, 2),
        ];

        // 9. إخراج النتيجة النهائية للقسيمة
        return [
            'employee_name'  => $employee->full_name,
            'period'         => "{$startDate} to {$endDate}",
            'contract_basic' => $basicSalary,
            'run_type'       => $runType->label(),
            'lines'          => $lines,
            'totals'         => [
                'total_allowances'            => round($totalAllowances, 2),
                'total_deductions'            => round($totalDeductions, 2),
                'total_company_contributions' => round($totalCompanyContributions, 2),
                'net_salary'                  => $netSalary,
            ],
            'disbursement'   => $disbursementInfo,
            'transparency'   => [
                'input_items'         => $automatedInputs['_INPUT_ITEMS'] ?? [],
                'loan_items'          => $automatedInputs['_LOAN_ITEMS'] ?? [],
                'absent_dates'        => $automatedInputs['_ABSENT_DATES'] ?? [],
                'unpaid_leave_ranges' => $automatedInputs['_UNPAID_LEAVE_RANGES'] ?? [],
                'delay_minutes'       => (int) ($automatedInputs['DELAY_MINUTES'] ?? 0),
                'early_leave_minutes' => (int) ($automatedInputs['EARLY_LEAVE_MINUTES'] ?? 0),
            ],
            'raw_inputs'     => $context,
        ];
    }

    /**
     * معالجة الصيغ الرياضية بأمان ووضوح عند وجود خطأ في الصياغة
     */
    protected function evaluateFormula(?string $formula, array $context, string $ruleCode = ''): float
    {
        if (empty($formula)) {
            return 0.0;
        }

        // تصفية السياق للاحتفاظ بالقيم الرقمية فقط ومنع حقن قيم منطقية كنصوص فارغة
        $numericContext = [];
        foreach ($context as $code => $value) {
            if (is_bool($value)) {
                $numericContext[$code] = $value ? '1' : '0';
            } elseif (is_int($value) || is_float($value) || is_numeric($value)) {
                $formatted = rtrim(rtrim(number_format((float) $value, 6, '.', ''), '0'), '.');
                $numericContext[$code] = ($formatted === '' || $formatted === '-') ? '0' : $formatted;
            }
        }

        // ترتيب المفاتيح من الأطول للأقصر لتجنب الاستبدال الجزئي الخاطئ
        uksort($numericContext, static fn (string $a, string $b): int => strlen($b) - strlen($a));

        $mathString = $formula;
        foreach ($numericContext as $code => $value) {
            $mathString = str_replace($code, $value, $mathString);
        }

        // حذف المسافات وتوحيد الإشارات المتتالية
        $mathString = str_replace(' ', '', $mathString);
        $mathString = str_replace(['--', '+-', '-+', '++'], ['+', '-', '-', '+'], $mathString);

        // الحماية من القسمة على صفر
        if (preg_match('/\/0(\.0+)?(?!\d)/', $mathString)) {
            return 0.0;
        }

        // التحقق الصارم من أن السلسلة تحتوي على أرقام وعمليات حسابية فقط
        if (!preg_match('/^[\d\.\+\-\*\/\(\)]+$/', $mathString)) {
            throw new Exception("خطأ في صيغة المعادلة الرياضية لقاعدة الراتب ({$ruleCode}): تحتوي على رموز أو متغيرات غير معرفة [{$formula}]");
        }

        try {
            $result = eval("return ({$mathString});");

            return is_numeric($result) ? (float) $result : 0.0;
        } catch (Throwable $e) {
            throw new Exception("تعذر احتساب المعادلة الرياضية لقاعدة الراتب ({$ruleCode}): {$e->getMessage()}");
        }
    }

    /**
     * جلب الموظفين المؤهلين لمسير الرواتب مرتبين أبجدياً بالاسم بناءً على مجموعة دفع الفترة وتواريخ سريان العقد
     */
    public function getEligibleEmployees(PayPeriod $period, PayrollRunType $runType, ?int $editingBatchId = null): Collection
    {
        $startDate = $period->start_date->format('Y-m-d');
        $endDate = $period->end_date->format('Y-m-d');

        // 1. جلب الموظفين النشطين مرتبين أبجدياً بالاسم الكامل
        $employees = Employee::query()
            ->with([
                'department:id,name',
                'position:id,name',
                'contracts',
                'activeContract',
                'currentContract',
            ])
            ->whereNotIn('status', [
                EmployeeStatus::Dismissed->value,
                EmployeeStatus::EndOfService->value,
            ])
            ->whereHas('contracts', function ($query) use ($period, $startDate, $endDate): void {
                $query->where('is_active', true)
                      ->where('pay_group_id', $period->pay_group_id)
                      ->whereNotNull('salary_structure_id')
                      ->where('start_date', '<=', $endDate)
                      ->where(function ($q) use ($startDate): void {
                          $q->whereNull('end_date')
                            ->orWhere('end_date', '>=', $startDate);
                      });
            })
            ->orderBy('full_name', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        // 2. جلب أرقام الموظفين الذين تم ترحيل رواتبهم في مسيرات أخرى لنفس الفترة ونوع المسير
        $processedEmployeeIds = Payslip::whereHas('batch', function ($query) use ($period, $runType, $editingBatchId): void {
            $query->where('status', 'posted')
                  ->where('pay_period_id', $period->id)
                  ->where('run_type', $runType->value);

            if ($editingBatchId !== null) {
                $query->where('id', '!=', $editingBatchId);
            }
        })
        ->distinct()
        ->pluck('employee_id')
        ->toArray();

        $processedSet = array_flip($processedEmployeeIds);

        // 3. حقن مؤشر is_processed لكل موظف مباشرة
        $employees->each(function (Employee $employee) use ($processedSet): void {
            $employee->is_processed = isset($processedSet[$employee->id]);
        });

        return $employees;
    }
}