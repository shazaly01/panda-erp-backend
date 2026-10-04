<?php

declare(strict_types=1);

namespace App\Modules\HR\Services;

use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\Contract;
use App\Modules\HR\Models\LoanInstallment;
use App\Modules\HR\Models\PayrollInput;
use App\Modules\HR\Models\AttendanceLog;
use App\Modules\HR\Models\OvertimePolicy;
use App\Modules\HR\Models\PayPeriod;
use App\Modules\HR\Enums\PayrollRunType;
use App\Modules\HR\Enums\SalaryRuleType;
use App\Modules\HR\Enums\SalaryRuleCategory;
use App\Modules\HR\Enums\EmployeeStatus;
use App\Modules\HR\Models\Payslip;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;
use Exception;

class PayrollService
{
    public function __construct(
        protected TimeEvaluationService $timeEvaluation
    ) {}

    /**
     * تجميع المدخلات المتغيرة للموظف في فترة معينة (آلياً)
     * تشمل: أقساط السلف المستحقة، الحوافز، الخصومات اليدوية، والغياب والتأخيرات.
     */
    protected function gatherAutomatedInputs(
        Employee $employee, 
        string $startDate, 
        string $endDate, 
        bool $isOvertimeOnly = false
    ): array {
        if ($isOvertimeOnly) {
            return []; // تجاهل السلف والخصومات في مسير الإضافي المنفصل
        }

        $inputs = [];

        // 1. جلب أقساط السلف المستحقة في هذه الفترة
        $installments = LoanInstallment::whereHas('loan', function ($q) use ($employee) {
                $q->where('employee_id', $employee->id);
            })
            ->where('status', 'pending')
            ->whereBetween('due_month', [$startDate, $endDate])
            ->get();

        $totalLoanDeduction = (float) $installments->sum('amount');
        if ($totalLoanDeduction > 0) {
            $inputs['LOAN_DEDUCTION'] = $totalLoanDeduction;
        }

        // 2. جلب الحوافز والخصومات اليدوية التي لم تُعالج بعد
        $payrollInputs = PayrollInput::where('employee_id', $employee->id)
            ->where('is_processed', false)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        $totalBonus = (float) $payrollInputs->where('type', 'bonus')->sum('amount');
        $totalPenalty = (float) $payrollInputs->where('type', 'penalty')->sum('amount');

        if ($totalBonus > 0) {
            $inputs['BONUS'] = $totalBonus;
        }

        if ($totalPenalty > 0) {
            $inputs['PENALTY'] = $totalPenalty;
        }

        // 3. جلب التأخيرات والغياب من واقع سجلات الحضور
        $attendanceLogs = AttendanceLog::where('employee_id', $employee->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        $totalDelayMinutes = (int) $attendanceLogs->sum('delay_minutes');
        $totalAbsentDays = (int) $attendanceLogs->where('status', 'absent')->count();

        $inputs['DELAY_MINUTES'] = $totalDelayMinutes;
        $inputs['ABSENT_DAYS'] = $totalAbsentDays;

        return $inputs;
    }

    /**
     * حساب قسيمة راتب افتراضية (Preview) لموظف معين لفترة محددة
     */
    public function previewPayslip(Employee $employee, PayPeriod $period, PayrollRunType $runType): array
    {
        // 1. جلب العقد النشط والساري مع حماية ضد العقود المنتهية
        $contract = $employee->activeContract ?? $employee->currentContract;

        if (!$contract) {
            throw new Exception("الموظف ({$employee->full_name}) ليس لديه عقد عمل نشط وسارٍ!");
        }

        // 2. التأكد من وجود هيكل رواتب مرتبط بالعقد وقواعده
        $salaryStructure = $contract->salaryStructure;
        if (!$salaryStructure) {
            throw new Exception("عقد الموظف ({$employee->full_name}) غير مربوط بهيكل رواتب معتمد!");
        }

        $rules = $salaryStructure->rules;
        if ($rules->isEmpty()) {
            throw new Exception("هيكل الرواتب ({$salaryStructure->name}) لا يحتوي على أي قواعد مالية!");
        }

        // استخراج تواريخ الفترة المالية
        $startDate = $period->start_date->format('Y-m-d');
        $endDate = $period->end_date->format('Y-m-d');
        $isOvertimeOnly = ($runType === PayrollRunType::OvertimeOnly);

        // 3. جلب سياسة الأوفرتايم المعتمدة أو تطبيق المعايير الافتراضية للشركات القياسية
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

        // 4. تقييم الحضور والأوفرتايم عبر محرك الساعات
        $automatedInputs = $this->gatherAutomatedInputs($employee, $startDate, $endDate, $isOvertimeOnly);
        $timeEvaluations = $this->timeEvaluation->evaluatePeriod($employee, $startDate, $endDate, $policy);

        // حساب عدد أيام الفترة المالية وأيام العمل الفعلية
        $periodDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;
        $absentDays = $automatedInputs['ABSENT_DAYS'] ?? 0;
        $workedDays = max(0, $periodDays - $absentDays);

        // 5. الحساب الديناميكي لأجر الساعة وفق سياسة المنشأة
        $basicSalary = (float) $contract->basic_salary;

        // نسبة وعاء الأجر (تكون 0.7600 للسياسة الحالية، أو 1.0000 كافتراضي للشركات العادية)
        $wageFactor = (float) ($policy->wage_factor > 0 ? $policy->wage_factor : 1.0000);

        // ساعات العمل المعيارية للشهر: تُقرأ من الساعات الثابتة (مثل 176) أو تُحسب آلياً من أيام وساعات الدوام
        $workingDaysPerMonth = (int) ($policy->working_days_per_month > 0 ? $policy->working_days_per_month : 30);
        $workingHoursPerDay = (float) ($policy->working_hours_per_day > 0 ? $policy->working_hours_per_day : 8.0);

        if (!empty($policy->fixed_monthly_hours) && (int) $policy->fixed_monthly_hours > 0) {
            $standardMonthlyHours = (float) $policy->fixed_monthly_hours;
        } else {
            $standardMonthlyHours = (float) ($workingDaysPerMonth * $workingHoursPerDay);
        }

        if ($standardMonthlyHours <= 0) {
            $standardMonthlyHours = 176.0; // حماية ضد القسمة على صفر
        }

        // أجر الساعة العادية الديناميكي
        $normalHourRate = ($basicSalary * $wageFactor) / $standardMonthlyHours;

        $holidayRate = (float) ($policy->holiday_rate > 0 ? $policy->holiday_rate : 2.0);
        $weekendRate = (float) ($policy->weekend_rate > 0 ? $policy->weekend_rate : 2.0);
        $regularRate = (float) ($policy->regular_rate > 0 ? $policy->regular_rate : 1.5);

        // استخراج ساعات العمل المنجزة
        $otRegularHours = (float) ($timeEvaluations['OT_REGULAR_HOURS'] ?? 0.0);
        $otWeekendHours = (float) ($timeEvaluations['OT_WEEKEND_HOURS'] ?? 0.0);
        $otHolidayHours = (float) ($timeEvaluations['OT_HOLIDAY_HOURS'] ?? 0.0);

        // حساب إجمالي الاستحقاق الإضافي قبل الضريبة (Gross)
        $holidayEarnings = $otHolidayHours * $normalHourRate * $holidayRate;
        $weekendEarnings = $otWeekendHours * $normalHourRate * $weekendRate;
        $regularEarnings = $otRegularHours * $normalHourRate * $regularRate;

        $grossOvertime = $holidayEarnings + $weekendEarnings + $regularEarnings;

        // التحقق الديناميكي من الفئات المعفاة من الضريبة المحددة في السياسة
        $exemptTypes = is_array($policy->tax_exempt_employment_types) && !empty($policy->tax_exempt_employment_types)
            ? array_map('strtolower', $policy->tax_exempt_employment_types)
            : ['daily', 'temporary', 'يومية', 'مؤقت'];

        $empType = strtolower((string) ($employee->employment_type?->value ?? $employee->employment_type ?? ''));
        $isDailyWorker = in_array($empType, $exemptTypes, true);

        // قراءة نسبة الضريبة من السياسة (مثلاً 15% أو 0% للشركات التي ليس لديها ضريبة إضافي)
        $configuredTaxRate = (float) ($policy->tax_rate ?? 0.0);
        $effectiveTaxRate = ($isDailyWorker || $configuredTaxRate <= 0) ? 0.0 : ($configuredTaxRate / 100.0);

        $overtimeTax = round($grossOvertime * $effectiveTaxRate, 2);
        $netOvertime = round($grossOvertime - $overtimeTax, 2);

        // 6. تهيئة ذاكرة السياق الحسابي (Context) بكافة القيم الديناميكية
        $context = array_merge([
            'BASIC'                => $isOvertimeOnly ? 0.0 : $basicSalary,
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
            'WORKED_DAYS'          => $workedDays,
            'OVERTIME_GROSS'       => round($grossOvertime, 2),
            'OVERTIME_TAX'         => $overtimeTax,
            'NET_OVERTIME'         => $netOvertime,
            'TAX_RATE'             => $configuredTaxRate,
            'IS_DAILY_WORKER'      => $isDailyWorker,
            'OVERTIME_AMOUNT'      => round($grossOvertime, 2),
        ], $automatedInputs, $timeEvaluations);

        $lines = [];
        $totalAllowances = 0.0;
        $totalDeductions = 0.0;
        $totalCompanyContributions = 0.0;

        // 7. تطبيق قواعد هيكل الراتب بالترتيب التسلسلي
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
                $amount = $baseValue * ((float)$rule->value / 100);
            } elseif ($rule->type === SalaryRuleType::Formula) {
                $amount = $this->evaluateFormula($rule->formula_expression, $context);
            } elseif ($rule->type === SalaryRuleType::Input) {
                $amount = (float) ($context[$code] ?? 0.0);
            }

            $context[$code] = $amount;

            if ($amount > 0) {
                if ($rule->category === SalaryRuleCategory::Allowance) {
                    $totalAllowances += $amount;
                } elseif ($rule->category === SalaryRuleCategory::Deduction) {
                    $totalDeductions += $amount;
                } elseif ($rule->category === SalaryRuleCategory::CompanyContribution) {
                    $totalCompanyContributions += $amount;
                }

                $lines[] = [
                    'code'     => $code,
                    'name'     => $rule->name,
                    'category' => $rule->category->value,
                    'amount'   => round((float)$amount, 2),
                ];
            }
        }

        // إدراج بند استقطاع ضريبة الإضافي تلقائياً فقط إذا وُجدت نسبة ضريبية مستحقة
        if ($overtimeTax > 0 && !collect($lines)->contains('code', 'OVERTIME_TAX')) {
            $totalDeductions += $overtimeTax;
            $lines[] = [
                'code'     => 'OVERTIME_TAX',
                'name'     => "ضريبة كسب العمل الإضافي ({$configuredTaxRate}%)",
                'category' => SalaryRuleCategory::Deduction->value,
                'amount'   => $overtimeTax,
            ];
        }

        // 8. إخراج النتيجة النهائية للقسيمة
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
                'net_salary'                  => round($totalAllowances - $totalDeductions, 2),
            ],
            'raw_inputs'     => $context,
        ];
    }

    /**
     * معالجة الصيغ الرياضية بأمان
     */
    protected function evaluateFormula(?string $formula, array $context): float
    {
        if (empty($formula)) {
            return 0.0;
        }

        // ترتيب المفاتيح من الأطول للأقصر لتجنب الاستبدال الجزئي الخاطئ
        uksort($context, function ($a, $b) {
            return strlen($b) - strlen($a);
        });

        $mathString = $formula;
        foreach ($context as $code => $value) {
            $mathString = str_replace($code, (string)$value, $mathString);
        }

        // حذف المسافات
        $mathString = str_replace(' ', '', $mathString);

        // الحماية من القسمة على صفر
        if (preg_match('/\/0(\.0+)?(?!\d)/', $mathString)) {
            return 0.0;
        }

        // التحقق من أن السلسلة تحتوي على أرقام وعمليات حسابية فقط
        if (!preg_match('/^[\d\.\+\-\*\/\(\)]+$/', $mathString)) {
            return 0.0;
        }

        try {
            return (float) eval("return $mathString;");
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    /**
     * جلب الموظفين المؤهلين لمسير الرواتب بناءً على مجموعة دفع الفترة وتواريخ سريان العقد مع تحديد حالة ترحيلهم مسبقاً
     */
    public function getEligibleEmployees(PayPeriod $period, PayrollRunType $runType): Collection
    {
        $startDate = $period->start_date->format('Y-m-d');
        $endDate = $period->end_date->format('Y-m-d');

        // 1. جلب الموظفين النشطين الذين لديهم عقد سارٍ متوافق مع مجموعة دفع وتواريخ الفترة وهيكل الرواتب
        $employees = Employee::query()
            ->with([
                'department:id,name',
                'position:id,name',
                'activeContract',
                'currentContract',
            ])
            ->whereNotIn('status', [
                EmployeeStatus::Dismissed->value,
                EmployeeStatus::EndOfService->value,
            ])
            ->whereHas('contracts', function ($query) use ($period, $startDate, $endDate) {
                $query->where('is_active', true)
                      ->where('pay_group_id', $period->pay_group_id)
                      ->whereNotNull('salary_structure_id')
                      ->where('start_date', '<=', $endDate)
                      ->where(function ($q) use ($startDate) {
                          $q->whereNull('end_date')
                            ->orWhere('end_date', '>=', $startDate);
                      });
            })
            ->latest('id')
            ->get();

        // 2. جلب أرقام الموظفين الذين تم ترحيل رواتبهم لنفس الفترة ونوع المسير
        $processedEmployeeIds = Payslip::whereHas('batch', function ($query) use ($period, $runType) {
            $query->where('status', 'posted')
                  ->where('pay_period_id', $period->id)
                  ->where('run_type', $runType->value);
        })
        ->distinct()
        ->pluck('employee_id')
        ->toArray();

        $processedSet = array_flip($processedEmployeeIds);

        // 3. حقن مؤشر is_processed لكل موظف مباشرة
        $employees->each(function (Employee $employee) use ($processedSet) {
            $employee->is_processed = isset($processedSet[$employee->id]);
        });

        return $employees;
    }
}