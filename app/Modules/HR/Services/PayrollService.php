<?php

declare(strict_types=1);

namespace App\Modules\HR\Services;

use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\Contract;
use App\Modules\HR\Models\LoanInstallment;
use App\Modules\HR\Models\PayrollInput;
use App\Modules\HR\Models\AttendanceLog;
use App\Modules\HR\Enums\SalaryRuleType;
use App\Modules\HR\Enums\SalaryRuleCategory;
use Carbon\Carbon;
use Exception;

class PayrollService
{
    /**
     * تجميع المدخلات المتغيرة للموظف في شهر معين (آلياً)
     */
    protected function gatherAutomatedInputs(Employee $employee, string $month): array
    {
        $inputs = [];
        $startOfMonth = Carbon::parse($month)->startOfMonth()->format('Y-m-d');
        $endOfMonth = Carbon::parse($month)->endOfMonth()->format('Y-m-d');

        // 1. جلب أقساط السلف المستحقة في هذا الشهر
        $installments = LoanInstallment::whereHas('loan', function ($q) use ($employee) {
                $q->where('employee_id', $employee->id);
            })
            ->where('status', 'pending')
            ->whereBetween('due_month', [$startOfMonth, $endOfMonth])
            ->get();

        $totalLoanDeduction = $installments->sum('amount');
        if ($totalLoanDeduction > 0) {
            $inputs['LOAN_DEDUCTION'] = $totalLoanDeduction;
        }

        // 2. جلب الحوافز والخصومات اليدوية التي لم تُعالج
        $payrollInputs = PayrollInput::where('employee_id', $employee->id)
            ->where('is_processed', false)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->get();

        $totalBonus = $payrollInputs->where('type', 'bonus')->sum('amount');
        $totalPenalty = $payrollInputs->where('type', 'penalty')->sum('amount');
        // يمكن إضافة allowance و deduction هنا أيضاً إذا دعت الحاجة

        if ($totalBonus > 0) $inputs['BONUS'] = $totalBonus;
        if ($totalPenalty > 0) $inputs['PENALTY'] = $totalPenalty;

        // 3. جلب التأخيرات والغياب من نظام الحضور
        // (كمثال: نحسب إجمالي دقائق التأخير ونحولها لخصم بناءً على الراتب الأساسي)
        $attendanceLogs = AttendanceLog::where('employee_id', $employee->id)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->get();

        $totalDelayMinutes = $attendanceLogs->sum('delay_minutes');
        $totalAbsentDays = $attendanceLogs->where('status', 'absent')->count();

        // تمريرها للمحرك ليقوم بحسابها عبر قواعد (Formula) أو نسبة مئوية
        $inputs['DELAY_MINUTES'] = $totalDelayMinutes;
        $inputs['ABSENT_DAYS'] = $totalAbsentDays;

        return $inputs;
    }

    /**
     * حساب قسيمة راتب افتراضية (Preview) لموظف معين لشهر محدد
     */
    public function previewPayslip(Employee $employee, string $month): array
    {
        // 1. جلب العقد النشط
        $contract = $employee->currentContract;

        if (!$contract) {
            throw new Exception("الموظف {$employee->full_name} ليس لديه عقد نشط!");
        }

        // 2. جلب القواعد من الهيكل المرتبط بالعقد
        $rules = $contract->salaryStructure->rules;

        // 3. تجميع المدخلات الآلية (السلف، الحضور، الإضافات)
        $automatedInputs = $this->gatherAutomatedInputs($employee, $month);

        // 4. ذاكرة المحرك (Context)
        $context = [
            'BASIC' => $contract->basic_salary,
            // حقن المتغيرات الآلية في الذاكرة لتستخدمها المعادلات
            'LOAN_DEDUCTION' => $automatedInputs['LOAN_DEDUCTION'] ?? 0,
            'BONUS'          => $automatedInputs['BONUS'] ?? 0,
            'PENALTY'        => $automatedInputs['PENALTY'] ?? 0,
            'DELAY_MINUTES'  => $automatedInputs['DELAY_MINUTES'] ?? 0,
            'ABSENT_DAYS'    => $automatedInputs['ABSENT_DAYS'] ?? 0,
        ];

        $lines = [];
        $totalAllowances = 0;
        $totalDeductions = 0;
        $totalCompanyContributions = 0;

        // 5. الدوران على القواعد وتنفيذها
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
                // النظام الآن يبحث عن القيمة في الـ Context الآلي بدلاً من الإدخال اليدوي
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
                    'code' => $code,
                    'name' => $rule->name,
                    'category' => $rule->category->value,
                    'amount' => round((float)$amount, 2),
                ];
            }
        }

        // 6. النتيجة النهائية
        return [
            'employee_name' => $employee->full_name,
            'month' => Carbon::parse($month)->format('Y-m'),
            'contract_basic' => $contract->basic_salary,
            'lines' => $lines,
            'totals' => [
                'total_allowances' => round($totalAllowances, 2),
                'total_deductions' => round($totalDeductions, 2),
                'total_company_contributions' => round($totalCompanyContributions, 2),
                'net_salary' => round($totalAllowances - $totalDeductions, 2),
            ],
            // نعيد الأرقام الخام للرجوع إليها عند الاعتماد (مثال: لمعرفة أي الأقساط نغير حالتها)
            'raw_inputs' => $automatedInputs
        ];
    }

    protected function evaluateFormula(?string $formula, array $context): float
    {
        // ... (هذه الدالة تبقى كما هي بدون تغيير لأنها ممتازة وتؤدي الغرض) ...
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
