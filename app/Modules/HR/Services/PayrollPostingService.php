<?php

declare(strict_types=1);

namespace App\Modules\HR\Services;

use App\Modules\Accounting\DTO\JournalEntryDetailDto;
use App\Modules\Accounting\DTO\JournalEntryDto;
use App\Modules\Accounting\Services\AccountMappingService;
use App\Modules\Accounting\Services\JournalEntryService;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\SalaryRule;
use App\Modules\HR\Models\LoanInstallment;
use App\Modules\HR\Models\PayrollInput;
use App\Modules\HR\Models\PayrollBatch;
use App\Modules\HR\Models\Payslip;
use App\Modules\HR\Models\PayPeriod; // <-- الإضافة
use App\Modules\HR\Enums\PayrollRunType; // <-- الإضافة
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Exception;

class PayrollPostingService
{
    public function __construct(
        protected PayrollService $payrollService,
        protected AccountMappingService $accountMappingService,
        protected JournalEntryService $journalEntryService
    ) {}

    /**
     * 🚀 تم التعديل: تستقبل الآن فترة مالية مقفلة ونوع التنفيذ بدلاً من تواريخ حرة
     */
    public function postPayrollBatch($employees, PayPeriod $period, PayrollRunType $runType, string $description): void
    {
        $groupedDebits = [];
        $groupedCredits = [];
        $employeePayables = [];
        $totalNetSalaries = 0;

        $startDate = $period->start_date->format('Y-m-d');
        $endDate = $period->end_date->format('Y-m-d');

        // 1. إنشاء رأس المسير (Batch Header) بالمعمارية الجديدة
        $payrollBatch = PayrollBatch::create([
            'name'          => mb_substr($description, 0, 255),
            'pay_period_id' => $period->id,
            'run_type'      => $runType->value,
            'status'        => 'posted',
            'approved_at'   => now(),
            'approved_by'   => Auth::id(),
        ]);

        $rules = SalaryRule::all()->keyBy('code');
        $payableAccountId = $this->accountMappingService->getAccountId('hr_salaries_payable');

        foreach ($employees as $employee) {
            $costCenterId = $employee->department ? $employee->department->cost_center_id : null;

            // 🚀 تمرير الفترة ونوع المسير للمحرك الذكي
            $payslipData = $this->payrollService->previewPayslip($employee, $period, $runType);

            // 2. حفظ "الصورة التذكارية" للراتب (Snapshot)
            Payslip::create([
                'payroll_batch_id' => $payrollBatch->id,
                'employee_id'      => $employee->id,
                'basic_salary'     => $payslipData['contract_basic'],
                'total_allowances' => $payslipData['totals']['total_allowances'],
                'total_deductions' => $payslipData['totals']['total_deductions'],
                'net_salary'       => $payslipData['totals']['net_salary'],
                'details'          => $payslipData['lines'],
            ]);

            $totalNetSalaries += $payslipData['totals']['net_salary'];

            // 3. تجميع القيود المحاسبية
            foreach ($payslipData['lines'] as $line) {
                $code = $line['code'];
                $amount = $line['amount'];
                if ($amount == 0) continue;

                $rule = $rules->get($code);
                if (!$rule || !$rule->account_mapping_key) {
                    throw new Exception("قاعدة الراتب {$code} ليس لها توجيه محاسبي!");
                }

                $accountId = $this->accountMappingService->getAccountId($rule->account_mapping_key);

                if ($line['category'] === 'allowance') {
                    $key = "{$accountId}_{$costCenterId}";
                    if (!isset($groupedDebits[$key])) {
                        $groupedDebits[$key] = ['account_id' => $accountId, 'cost_center_id' => $costCenterId, 'amount' => 0];
                    }
                    $groupedDebits[$key]['amount'] += $amount;
                }
                elseif ($line['category'] === 'deduction') {
                    if (!isset($groupedCredits[$accountId])) $groupedCredits[$accountId] = 0;
                    $groupedCredits[$accountId] += $amount;
                }
            }

            // إضافة تفاصيل الموظف للقيد
            $employeePayables[] = new JournalEntryDetailDto(
                account_id: $payableAccountId,
                debit: 0,
                credit: $payslipData['totals']['net_salary'],
                description: "رواتب مستحقة - {$employee->full_name}",
                party_type: 'employee',
                party_id: (string) $employee->id,
                cost_center_id: null
            );

            // 4. إغلاق العهد والسلف والمدخلات اليدوية بناءً على التواريخ الدقيقة
            // 🚀 الذكاء هنا: يتم إغلاق السلف والخصومات فقط إذا كان المسير اعتيادياً (Regular)
            if ($runType === PayrollRunType::Regular) {
                LoanInstallment::whereHas('loan', fn($q) => $q->where('employee_id', $employee->id))
                    ->where('status', 'pending')
                    ->whereBetween('due_month', [$startDate, $endDate])
                    ->update(['status' => 'deducted']);

                PayrollInput::where('employee_id', $employee->id)
                    ->where('is_processed', false)
                    ->whereBetween('date', [$startDate, $endDate])
                    ->update(['is_processed' => true]);
            }
        }

        // 5. بناء القيد المحاسبي النهائي
        $journalDetails = [];
        foreach ($groupedDebits as $debit) {
            $journalDetails[] = new JournalEntryDetailDto(
                account_id: $debit['account_id'],
                debit: $debit['amount'],
                credit: 0,
                cost_center_id: $debit['cost_center_id'],
                description: "مصروفات رواتب - $description"
            );
        }
        foreach ($groupedCredits as $accountId => $amount) {
            $journalDetails[] = new JournalEntryDetailDto(
                account_id: $accountId,
                debit: 0,
                credit: $amount,
                description: "استقطاعات رواتب - $description"
            );
        }

        $journalDetails = array_merge($journalDetails, $employeePayables);

        // نستخدم $endDate كتاريخ استحقاق القيد المحاسبي
        $journalEntryDto = new JournalEntryDto(
            date: $endDate,
            details: $journalDetails,
            description: $description
        );

        $journalEntry = $this->journalEntryService->createEntry($journalEntryDto);

        $payrollBatch->update([
            'journal_entry_id' => $journalEntry->id,
        ]);

        // 6. إغلاق الفترة المالية بعد الصرف الأساسي
        // نغلق الفترة فقط إذا كان الصرف أساسياً لتجنب الازدواجية
        if ($runType === PayrollRunType::Regular) {
            $period->update(['status' => 'closed']);
        }
    }
}
