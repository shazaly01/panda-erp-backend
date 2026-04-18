<?php

declare(strict_types=1);

namespace App\Modules\HR\Services;

use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\Contract;
use App\Modules\HR\Models\LoanInstallment;
use App\Modules\HR\Models\PayrollInput;
use App\Modules\HR\Models\AttendanceLog;
use App\Modules\HR\Models\OvertimePolicy;
use App\Modules\HR\Models\PayPeriod; // <-- الإضافة: نموذج الفترة المالية
use App\Modules\HR\Enums\PayrollRunType; // <-- الإضافة: نوع المسير
use App\Modules\HR\Enums\SalaryRuleType;
use App\Modules\HR\Enums\SalaryRuleCategory;
use Carbon\Carbon;
use Exception;

class PayrollService
{
    // 1. حقن خدمة تقييم الوقت التي أنشأناها في الخطوة السابقة
    public function __construct(
        protected TimeEvaluationService $timeEvaluation
    ) {}

    /**
     * تجميع المدخلات المتغيرة للموظف في فترة معينة (آلياً)
     * 🚀 تم التحديث: إضافة $isOvertimeOnly لتجاهل السلف والحوافز في مسيرات الإضافي
     */
    protected function gatherAutomatedInputs(Employee $employee, string $startDate, string $endDate, bool $isOvertimeOnly = false): array
    {
        if ($isOvertimeOnly) {
            return []; // تجاهل كل شيء (الغياب، التأخير، السلف) في مسير الإضافي
        }

        $inputs = [];

        // 1. جلب أقساط السلف المستحقة في هذه الفترة
        $installments = LoanInstallment::whereHas('loan', function ($q) use ($employee) {
                $q->where('employee_id', $employee->id);
            })
            ->where('status', 'pending')
            ->whereBetween('due_month', [$startDate, $endDate])
            ->get();

        $totalLoanDeduction = $installments->sum('amount');
        if ($totalLoanDeduction > 0) {
            $inputs['LOAN_DEDUCTION'] = $totalLoanDeduction;
        }

        // 2. جلب الحوافز والخصومات اليدوية التي لم تُعالج خلال الفترة
        $payrollInputs = PayrollInput::where('employee_id', $employee->id)
            ->where('is_processed', false)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        $totalBonus = $payrollInputs->where('type', 'bonus')->sum('amount');
        $totalPenalty = $payrollInputs->where('type', 'penalty')->sum('amount');

        if ($totalBonus > 0) $inputs['BONUS'] = $totalBonus;
        if ($totalPenalty > 0) $inputs['PENALTY'] = $totalPenalty;

        // 3. جلب التأخيرات والغياب من نظام الحضور خلال الفترة
        $attendanceLogs = AttendanceLog::where('employee_id', $employee->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        $totalDelayMinutes = $attendanceLogs->sum('delay_minutes');
        $totalAbsentDays = $attendanceLogs->where('status', 'absent')->count();

        $inputs['DELAY_MINUTES'] = $totalDelayMinutes;
        $inputs['ABSENT_DAYS'] = $totalAbsentDays;

        return $inputs;
    }

    /**
     * حساب قسيمة راتب افتراضية (Preview) لموظف معين لفترة محددة
     * 🚀 تم التحديث: لتستقبل الكيانات الجديدة (PayPeriod, PayrollRunType)
     */
    public function previewPayslip(Employee $employee, PayPeriod $period, PayrollRunType $runType): array
    {
        // 1. جلب العقد النشط
        $contract = $employee->currentContract;

        if (!$contract) {
            throw new Exception("الموظف {$employee->full_name} ليس لديه عقد نشط!");
        }

        // استخراج التواريخ من الفترة
        $startDate = $period->start_date->format('Y-m-d');
        $endDate = $period->end_date->format('Y-m-d');
        $isOvertimeOnly = ($runType === PayrollRunType::OvertimeOnly);

        // 2. جلب القواعد من الهيكل المرتبط بالعقد
        $rules = $contract->salaryStructure->rules;

        // 3. جلب سياسة الأوفرتايم
        $policy = $contract->overtimePolicy ?? new OvertimePolicy([
            'working_days_per_month' => 30,
            'working_hours_per_day' => 8,
            'regular_rate' => 1.5,
            'weekend_rate' => 2.0,
            'holiday_rate' => 2.0,
            'is_daily_basis' => false,
        ]);

        // 4. تجميع المدخلات الآلية وتقييم الوقت
        $automatedInputs = $this->gatherAutomatedInputs($employee, $startDate, $endDate, $isOvertimeOnly);
        $timeEvaluations = $this->timeEvaluation->evaluatePeriod($employee, $startDate, $endDate, $policy);

        // حساب أيام الفترة بناءً على التواريخ
        $periodDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;

        // حساب أيام العمل الفعلية (أيام الفترة ناقص أيام الغياب إن وجدت)
        $absentDays = $automatedInputs['ABSENT_DAYS'] ?? 0;
        $workedDays = max(0, $periodDays - $absentDays);

        // 5. حساب معدل اليوم ومعدل الساعة ديناميكياً
        $dayRate = $contract->basic_salary / ($policy->working_days_per_month ?: 30);
        $hourRate = $dayRate / ($policy->working_hours_per_day ?: 8);

        // --- جلب ساعات الأوفرتايم ---
        $otRegularHours = $timeEvaluations['OT_REGULAR_HOURS'] ?? 0;
        $otWeekendHours = $timeEvaluations['OT_WEEKEND_HOURS'] ?? 0;
        $otHolidayHours = $timeEvaluations['OT_HOLIDAY_HOURS'] ?? 0;

        // --- جلب أيام الأوفرتايم (الإضافة الجديدة لدعم ذكاء TimeEvaluationService) ---
        $otRegularDays = $timeEvaluations['OT_REGULAR_DAYS'] ?? 0;
        $otWeekendDays = $timeEvaluations['OT_WEEKEND_DAYS'] ?? 0;
        $otHolidayDays = $timeEvaluations['OT_HOLIDAY_DAYS'] ?? 0;

        // --- حساب القيمة النقدية الشاملة للأوفرتايم (ساعات + أيام) ---
        $overtimeAmount =
            // حساب الساعات (مضروبة في أجر الساعة)
            ($otRegularHours * $hourRate * $policy->regular_rate) +
            ($otWeekendHours * $hourRate * $policy->weekend_rate) +
            ($otHolidayHours * $hourRate * $policy->holiday_rate) +
            // حساب الأيام (مضروبة في أجر اليوم)
            ($otRegularDays * $dayRate * $policy->regular_rate) +
            ($otWeekendDays * $dayRate * $policy->weekend_rate) +
            ($otHolidayDays * $dayRate * $policy->holiday_rate);

        // 6. ذاكرة المحرك (Context)
        $context = array_merge([
            // 🚀 التعديل: تصفير الأساسي في سياق العمليات الحسابية إذا كان إضافي فقط
            'BASIC'           => $isOvertimeOnly ? 0 : $contract->basic_salary,
            'DAY_RATE'        => round($dayRate, 4),
            'HOUR_RATE'       => round($hourRate, 4),
            'OT_REG_RATE'     => $policy->regular_rate,
            'OT_WKD_RATE'     => $policy->weekend_rate,
            'OT_HOL_RATE'     => $policy->holiday_rate,

            // المتغيرات الديناميكية الجديدة
            'PERIOD_DAYS'     => $periodDays,
            'WORKED_DAYS'     => $workedDays,
            'OVERTIME_AMOUNT' => round($overtimeAmount, 2),
        ], $automatedInputs, $timeEvaluations);

        $lines = [];
        $totalAllowances = 0;
        $totalDeductions = 0;
        $totalCompanyContributions = 0;

        // 7. الدوران على القواعد وتنفيذها
        foreach ($rules as $rule) {
            $amount = 0;
            $code = $rule->code;

            // 🚀 توجيه المحرك: في مسير الإضافي، نتخطى كل القواعد باستثناء "OVERTIME_AMOUNT"
            if ($isOvertimeOnly && $code !== 'OVERTIME_AMOUNT') {
                continue;
            }

            if ($rule->type === SalaryRuleType::Fixed) {
                // نأخذ قيمة BASIC من السياق (Context) لضمان أنها 0 في مسير الإضافي
                $amount = ($code === 'BASIC') ? $context['BASIC'] : $rule->value;
            } elseif ($rule->type === SalaryRuleType::Percentage) {
                $baseValue = $context[$rule->percentage_of_code] ?? 0;
                $amount = $baseValue * ($rule->value / 100);
            } elseif ($rule->type === SalaryRuleType::Formula) {
                $amount = $this->evaluateFormula($rule->formula_expression, $context);
            } elseif ($rule->type === SalaryRuleType::Input) {
                $amount = $context[$code] ?? 0;
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

        // 8. النتيجة النهائية
        return [
            'employee_name'  => $employee->full_name,
            'period'         => "{$startDate} to {$endDate}",
            'contract_basic' => $contract->basic_salary,
            'run_type'       => $runType->label(), // للتوثيق
            'lines'          => $lines,
            'totals'         => [
                'total_allowances'            => round($totalAllowances, 2),
                'total_deductions'            => round($totalDeductions, 2),
                'total_company_contributions' => round($totalCompanyContributions, 2),
                'net_salary'                  => round($totalAllowances - $totalDeductions, 2),
            ],
            'raw_inputs' => array_merge($context)
        ];
    }

    protected function evaluateFormula(?string $formula, array $context): float
    {
        if (empty($formula)) return 0;

        uksort($context, function($a, $b) {
            return strlen($b) - strlen($a);
        });

        $mathString = $formula;
        foreach ($context as $code => $value) {
            $mathString = str_replace($code, (string)$value, $mathString);
        }

        if (!preg_match('/^[\d\.\+\-\*\/\(\)\s]+$/', $mathString)) {
            return 0;
        }

        try {
            return (float) eval("return $mathString;");
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
