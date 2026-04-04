<?php

declare(strict_types=1);

namespace App\Modules\HR\Services;

use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\Contract;
use App\Modules\HR\Enums\SalaryRuleType;
use App\Modules\HR\Enums\SalaryRuleCategory;
use Exception;

class PayrollService
{
    /**
     * حساب قسيمة راتب افتراضية (Preview) لموظف معين
     * * @param Employee $employee الموظف
     * @param array $externalInputs القيم الخارجية (سلف، غياب) مثال: ['LOAN' => 500, 'ABSENCE' => 100]
     * @return array تفاصيل الراتب
     */
    public function previewPayslip(Employee $employee, array $externalInputs = []): array
    {
        // 1. جلب العقد النشط
        $contract = $employee->currentContract;

        if (!$contract) {
            throw new Exception("الموظف {$employee->full_name} ليس لديه عقد نشط!");
        }

        // 2. جلب القواعد من الهيكل المرتبط بالعقد (مرتبة حسب الـ Sequence)
        $rules = $contract->salaryStructure->rules;

        // 3. ذاكرة المحرك (Context) لتخزين القيم المحسوبة واستخدامها في المعادلات اللاحقة
        // نبدأ بتعريف القيمة الجوهرية: BASIC
        $context = [
            'BASIC' => $contract->basic_salary,
        ];

        $lines = [];
        $totalAllowances = 0;
        $totalDeductions = 0;

        // 4. الدوران على القواعد وتنفيذها
        foreach ($rules as $rule) {
            $amount = 0;
            $code = $rule->code;

            // أ. نوع الحساب: ثابت
            if ($rule->type === SalaryRuleType::Fixed) {
                // إذا كان BASIC نأخذه من العقد، غير ذلك نأخذه من تعريف القاعدة
                $amount = ($code === 'BASIC') ? $contract->basic_salary : $rule->value;
            }

            // ب. نوع الحساب: نسبة مئوية
            elseif ($rule->type === SalaryRuleType::Percentage) {
                // مثال: 10% من BASIC
                $baseValue = $context[$rule->percentage_of_code] ?? 0;
                $amount = $baseValue * ($rule->value / 100);
            }

            // ج. نوع الحساب: معادلة (Formula)
            elseif ($rule->type === SalaryRuleType::Formula) {
                // مثال: (BASIC + HOUSING) * 0.10
                $amount = $this->evaluateFormula($rule->formula_expression, $context);
            }

            // د. نوع الحساب: مدخل خارجي (Input)
            elseif ($rule->type === SalaryRuleType::Input) {
                // نبحث عنه في المدخلات الخارجية (السلف، الغياب)
                $amount = $externalInputs[$code] ?? 0;
            }

            // تخزين النتيجة في الذاكرة لاستخدامها في القواعد التالية
            $context[$code] = $amount;

            // تجميع الإجماليات
            if ($amount > 0) {
                if ($rule->category === SalaryRuleCategory::Allowance) {
                    $totalAllowances += $amount;
                } elseif ($rule->category === SalaryRuleCategory::Deduction) {
                    $totalDeductions += $amount;
                }

                // إضافة السطر للكشف
                $lines[] = [
                    'code' => $code,
                    'name' => $rule->name,
                    'category' => $rule->category->value,
                    'amount' => round((float)$amount, 2),
                ];
            }
        }

        // 5. النتيجة النهائية
        return [
            'employee_name' => $employee->full_name,
            'contract_basic' => $contract->basic_salary,
            'lines' => $lines,
            'totals' => [
                'total_allowances' => round($totalAllowances, 2),
                'total_deductions' => round($totalDeductions, 2),
                'net_salary' => round($totalAllowances - $totalDeductions, 2),
            ]
        ];
    }

    /**
     * معالج المعادلات البسيط
     * يقوم باستبدال الكود بالقيمة وحساب الناتج
     */
    protected function evaluateFormula(?string $formula, array $context): float
    {
        if (empty($formula)) return 0;

        // استبدال الرموز (BASIC, HOUSING) بالقيم (5000, 1250)
        // نقوم بترتيب المفاتيح من الأطول للأقصر لتجنب استبدال جزء من الكلمة
        uksort($context, function($a, $b) {
            return strlen($b) - strlen($a);
        });

        $mathString = $formula;
        foreach ($context as $code => $value) {
            $mathString = str_replace($code, (string)$value, $mathString);
        }

        // تنظيف المعادلة للسماح فقط بالأرقام والعمليات الحسابية (للأمان)
        if (!preg_match('/^[\d\.\+\-\*\/\(\)\s]+$/', $mathString)) {
            // لو وجدنا رموزاً غريبة، نعيد صفر حماية للنظام
            return 0;
        }

        // تنفيذ المعادلة
        // ملاحظة: في بيئة الإنتاج الحقيقية نستخدم مكتبة مثل symfony/expression-language
        // لكن هنا نستخدم eval بحذر شديد وبعد التحقق من المحتوى (Sanitization)
        try {
            return (float) eval("return $mathString;");
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
