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
use Illuminate\Support\Collection;
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
     * ترحيل الرواتب وإنشاء قيد محاسبي موحد ومفصل
     */
    public function postPayrollBatch($employees, string $date, string $description): void
    {
        // استخراج صيغة الشهر (مثال: 2026-04) لتمريرها للمحرك
        $month = Carbon::parse($date)->format('Y-m');
        $startOfMonth = Carbon::parse($month)->startOfMonth()->format('Y-m-d');
        $endOfMonth = Carbon::parse($month)->endOfMonth()->format('Y-m-d');

        $groupedDebits = [];
        $groupedCredits = [];
        $employeePayables = [];

        $rules = SalaryRule::all()->keyBy('code');
        $payableAccountId = $this->accountMappingService->getAccountId('hr_salaries_payable');
        $contributionsPayableAccountId = $this->accountMappingService->getAccountId('hr_contributions_payable');

        foreach ($employees as $employee) {
            $costCenterId = $employee->department ? $employee->department->cost_center_id : null;

            // 1. استدعاء المحرك مع تمرير الشهر المطلوب
            $payslip = $this->payrollService->previewPayslip($employee, $month);

            foreach ($payslip['lines'] as $line) {
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
                elseif ($line['category'] === 'company_contribution') {
                    $key = "{$accountId}_{$costCenterId}";
                    if (!isset($groupedDebits[$key])) {
                        $groupedDebits[$key] = ['account_id' => $accountId, 'cost_center_id' => $costCenterId, 'amount' => 0];
                    }
                    $groupedDebits[$key]['amount'] += $amount;

                    if (!isset($groupedCredits[$contributionsPayableAccountId])) {
                        $groupedCredits[$contributionsPayableAccountId] = 0;
                    }
                    $groupedCredits[$contributionsPayableAccountId] += $amount;
                }
            }

            $employeePayables[] = new JournalEntryDetailDto(
                account_id: $payableAccountId,
                debit: 0,
                credit: $payslip['totals']['net_salary'],
                description: "رواتب مستحقة - {$employee->full_name}",
                party_type: 'employee',
                party_id: clone $employee->employee_number, // استخدام الرقم الطويل
                cost_center_id: null
            );

            // 2. إقفال الحركات المالية المؤقتة لهذا الموظف (لضمان عدم تكرار الخصم/المنح)

            // إقفال أقساط السلف
            LoanInstallment::whereHas('loan', function ($q) use ($employee) {
                    $q->where('employee_id', $employee->id);
                })
                ->where('status', 'pending')
                ->whereBetween('due_month', [$startOfMonth, $endOfMonth])
                ->update(['status' => 'deducted']);

            // إقفال الحوافز والخصومات
            PayrollInput::where('employee_id', $employee->id)
                ->where('is_processed', false)
                ->whereBetween('date', [$startOfMonth, $endOfMonth])
                ->update(['is_processed' => true]);
        }

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
                description: "استقطاعات والتزامات - $description"
            );
        }

        $journalDetails = array_merge($journalDetails, $employeePayables);

        $journalEntryDto = new JournalEntryDto(
            date: $date,
            details: $journalDetails,
            description: $description
        );

        $this->journalEntryService->createEntry($journalEntryDto);
    }
}
