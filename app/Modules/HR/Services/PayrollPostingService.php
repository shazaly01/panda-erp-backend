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
// استيراد الموديلات الجديدة
use App\Modules\HR\Models\PayrollBatch;
use App\Modules\HR\Models\Payslip;
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

    public function postPayrollBatch($employees, string $date, string $description): void
    {
        $month = Carbon::parse($date)->format('Y-m');
        $startOfMonth = Carbon::parse($month)->startOfMonth()->format('Y-m-d');
        $endOfMonth = Carbon::parse($month)->endOfMonth()->format('Y-m-d');

        $groupedDebits = [];
        $groupedCredits = [];
        $employeePayables = [];
        $totalNetSalaries = 0;

        // 1. إنشاء رأس المسير (Batch Header)
        $payrollBatch = PayrollBatch::create([
            'date' => $startOfMonth,
            'description' => $description,
            'status' => 'posted',
            'total_amount' => 0, // سيتم تحديثه لاحقاً
            'created_by' => Auth::id(),
        ]);

        $rules = SalaryRule::all()->keyBy('code');
        $payableAccountId = $this->accountMappingService->getAccountId('hr_salaries_payable');
        $contributionsPayableAccountId = $this->accountMappingService->getAccountId('hr_contributions_payable');

        foreach ($employees as $employee) {
            $costCenterId = $employee->department ? $employee->department->cost_center_id : null;

            // حساب الراتب عبر المحرك
            $payslipData = $this->payrollService->previewPayslip($employee, $month);

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

            // تجميع القيود المحاسبية (المنطق الحالي كما هو)
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
                party_id: (int) $employee->employee_number,
                cost_center_id: null
            );

            // إغلاق العهد والسلف
            LoanInstallment::whereHas('loan', fn($q) => $q->where('employee_id', $employee->id))
                ->where('status', 'pending')
                ->whereBetween('due_month', [$startOfMonth, $endOfMonth])
                ->update(['status' => 'deducted']);

            PayrollInput::where('employee_id', $employee->id)
                ->where('is_processed', false)
                ->whereBetween('date', [$startOfMonth, $endOfMonth])
                ->update(['is_processed' => true]);
        }

        // بناء القيد المحاسبي النهائي
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

        $journalEntryDto = new JournalEntryDto(
            date: $date,
            details: $journalDetails,
            description: $description
        );

        // 3. إنشاء القيد وربطه بالمسير
        $journalEntry = $this->journalEntryService->createEntry($journalEntryDto);

        $payrollBatch->update([
            'total_amount' => $totalNetSalaries,
            'journal_entry_id' => $journalEntry->id,
        ]);
    }
}
