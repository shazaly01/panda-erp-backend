<?php

declare(strict_types=1);

namespace App\Modules\HR\Services;

use App\Modules\Accounting\DTO\JournalEntryDetailDto;
use App\Modules\Accounting\DTO\JournalEntryDto;
use App\Modules\Accounting\Services\AccountMappingService;
use App\Modules\Accounting\Services\JournalEntryService;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\SalaryRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Exception;

class PayrollPostingService
{
    public function __construct(
        protected PayrollService $payrollService,
        protected AccountMappingService $accountMappingService,
        protected JournalEntryService $journalEntryService
    ) {}

    /**
     * ترحيل الرواتب وإنشاء قيد محاسبي موحد
     * @param Employee[]|Collection $employees قائمة الموظفين
     * @param string $date تاريخ الاستحقاق (نهاية الشهر)
     * @param string $description شرح القيد
     */
    public function postPayrollBatch($employees, string $date, string $description): void
    {
        // 1. مصفوفة لتجميع المبالغ حسب الحساب (Account ID)
        // الشكل: [ 'account_id' => amount ]
        $aggregatedDebits = [];  // للمصروفات (بدلات)
        $aggregatedCredits = []; // للاستقطاعات (خصومات)

        $totalNetSalary = 0; // إجمالي صافي الرواتب (سيكون الدائن الرئيسي)

        // جلب كل قواعد الراتب لنعرف مفاتيح التوجيه الخاصة بها
        $rules = SalaryRule::all()->keyBy('code');

        // 2. الدوران على الموظفين وحساب الرواتب
        foreach ($employees as $employee) {
            // حساب الراتب (بدون حفظ، فقط Preview)
            $payslip = $this->payrollService->previewPayslip($employee);

            // التعامل مع سطور الراتب
            foreach ($payslip['lines'] as $line) {
                $code = $line['code'];
                $amount = $line['amount'];

                // تخطي القيم الصفرية
                if ($amount == 0) continue;

                // العثور على قاعدة الراتب لجلب مفتاح التوجيه
                $rule = $rules->get($code);
                if (!$rule || !$rule->account_mapping_key) {
                    throw new Exception("قاعدة الراتب {$code} ليس لها مفتاح توجيه محاسبي (Account Mapping Key)!");
                }

                // ترجمة المفتاح إلى رقم حساب فعلي
                $accountId = $this->accountMappingService->getAccountId($rule->account_mapping_key);

                // التصنيف: هل هو استحقاق (Debit) أم استقطاع (Credit)؟
                if ($line['category'] === 'allowance') {
                    // المصروفات طبيعتها مدينة
                    if (!isset($aggregatedDebits[$accountId])) $aggregatedDebits[$accountId] = 0;
                    $aggregatedDebits[$accountId] += $amount;
                } else {
                    // الخصومات طبيعتها دائنة (التزامات للغير)
                    if (!isset($aggregatedCredits[$accountId])) $aggregatedCredits[$accountId] = 0;
                    $aggregatedCredits[$accountId] += $amount;
                }
            }

            // تجميع صافي الراتب (Net Salary)
            // هذا المبلغ هو التزام على الشركة تجاه الموظفين
            $totalNetSalary += $payslip['totals']['net_salary'];
        }

        // 3. تجهيز أسطر القيد (DTOs)
        $journalDetails = [];

        // أ. إضافة سطور المصروفات (المدين)
        foreach ($aggregatedDebits as $accountId => $amount) {
            $journalDetails[] = new JournalEntryDetailDto(
                account_id: $accountId,
                debit: $amount,
                credit: 0,
                description: "إجمالي " . $this->getAccountName($rules, $accountId) // اختياري: تحسين الوصف
            );
        }

        // ب. إضافة سطور الخصومات (الدائن)
        foreach ($aggregatedCredits as $accountId => $amount) {
            $journalDetails[] = new JournalEntryDetailDto(
                account_id: $accountId,
                debit: 0,
                credit: $amount
            );
        }

        // ج. إضافة سطر صافي الرواتب المستحقة (الدائن المكمل للقيد)
        // يجب أن يكون لدينا مفتاح ثابت للرواتب المستحقة، مثلاً 'hr_salaries_payable'
        $payableAccountId = $this->accountMappingService->getAccountId('hr_salaries_payable');

        $journalDetails[] = new JournalEntryDetailDto(
            account_id: $payableAccountId,
            debit: 0,
            credit: $totalNetSalary,
            description: "صافي رواتب مستحقة - $description"
        );

        // 4. بناء كائن القيد كاملاً
        $journalEntryDto = new JournalEntryDto(
            date: $date,
            details: $journalDetails,
            description: $description
        );

        // 5. إرسال القيد للمحاسبة
        $this->journalEntryService->createEntry($journalEntryDto);
    }

    // دالة مساعدة لجلب الاسم (يمكن تحسينها)
    private function getAccountName($rules, $accountId) {
        return "مصروفات رواتب";
    }
}
