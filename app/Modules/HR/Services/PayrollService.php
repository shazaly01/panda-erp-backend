<?php

declare(strict_types=1);

namespace App\Modules\HR\Services;

use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\Contract;
use App\Modules\HR\Models\LoanInstallment;
use App\Modules\HR\Models\PayrollInput;
use App\Modules\HR\Models\AttendanceLog;
use App\Modules\HR\Models\OvertimePolicy; // <-- تمت الإضافة
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
     * تم التعديل لتستقبل تاريخ بداية ونهاية بدلاً من شهر
     */
    protected function gatherAutomatedInputs(Employee $employee, string $startDate, string $endDate): array
    {
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
     * تم التعديل لتستقبل تاريخ بداية ونهاية لتدعم الأسبوعي والشهري
     */
    public function previewPayslip(Employee $employee, string $startDate, string $endDate): array
    {
        // 1. جلب العقد النشط
        $contract = $employee->currentContract;

        if (!$contract) {
            throw new Exception("الموظف {$employee->full_name} ليس لديه عقد نشط!");
        }

        // 2. جلب القواعد من الهيكل المرتبط بالعقد
        $rules = $contract->salaryStructure->rules;

        // 3. جلب سياسة الأوفرتايم (وضعنا سياسة افتراضية في الذاكرة لتجنب الأخطاء إذا نسي المستخدم ربط السياسة بالعقد)
        $policy = $contract->overtimePolicy ?? new OvertimePolicy([
            'working_days_per_month' => 30,
            'working_hours_per_day' => 8,
            'regular_rate' => 1.5,
            'weekend_rate' => 2.0,
            'holiday_rate' => 2.0,
            'is_daily_basis' => false,
        ]);

        // 4. تجميع المدخلات الآلية وتقييم الوقت
        $automatedInputs = $this->gatherAutomatedInputs($employee, $startDate, $endDate);

        // 🚀 السحر هنا: تمرير التواريخ والسياسة لخدمة تقييم الوقت
        $timeEvaluations = $this->timeEvaluation->evaluatePeriod($employee, $startDate, $endDate, $policy);

        // 5. حساب معدل اليوم ومعدل الساعة ديناميكياً
        $dayRate = $contract->basic_salary / ($policy->working_days_per_month ?: 30);
        $hourRate = $dayRate / ($policy->working_hours_per_day ?: 8);

        // 6. ذاكرة المحرك (Context)
        // دمج كل المتغيرات (الأساسي، قيمة اليوم، قيمة الساعة، معاملات الأوفرتايم، أيام الأوفرتايم وساعاته)
        $context = array_merge([
            'BASIC'       => $contract->basic_salary,
            'DAY_RATE'    => round($dayRate, 4),
            'HOUR_RATE'   => round($hourRate, 4),
            'OT_REG_RATE' => $policy->regular_rate,
            'OT_WKD_RATE' => $policy->weekend_rate,
            'OT_HOL_RATE' => $policy->holiday_rate,
        ], $automatedInputs, $timeEvaluations);

        $lines = [];
        $totalAllowances = 0;
        $totalDeductions = 0;
        $totalCompanyContributions = 0;

        // 7. الدوران على القواعد وتنفيذها (لا تغيير هنا، الكود الخاص بك ممتاز)
        foreach ($rules as $rule) {
            $amount = 0;
            $code = $rule->code;

            if ($rule->type === SalaryRuleType::Fixed) {
                $amount = ($code === 'BASIC') ? $contract->basic_salary : $rule->value;
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
            'period'         => "{$startDate} to {$endDate}", // استبدلنا month بالفترة لدعم الرواتب الأسبوعية
            'contract_basic' => $contract->basic_salary,
            'lines'          => $lines,
            'totals'         => [
                'total_allowances'            => round($totalAllowances, 2),
                'total_deductions'            => round($totalDeductions, 2),
                'total_company_contributions' => round($totalCompanyContributions, 2),
                'net_salary'                  => round($totalAllowances - $totalDeductions, 2),
            ],
            // جمعنا كل المتغيرات هنا للرجوع إليها عند مراجعة تفاصيل القسيمة (Debug)
            'raw_inputs' => array_merge($automatedInputs, $timeEvaluations)
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
