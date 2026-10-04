<?php

declare(strict_types=1);

namespace App\Modules\HR\Services;

use App\Modules\Accounting\DTO\JournalEntryDetailDto;
use App\Modules\Accounting\DTO\JournalEntryDto;
use App\Modules\Accounting\Enums\EntryStatus;
use App\Modules\Accounting\Services\AccountMappingService;
use App\Modules\Accounting\Services\JournalEntryService;
use App\Modules\Core\Services\SequenceService;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\SalaryRule;
use App\Modules\HR\Models\LoanInstallment;
use App\Modules\HR\Models\PayrollInput;
use App\Modules\HR\Models\PayrollBatch;
use App\Modules\HR\Models\Payslip;
use App\Modules\HR\Models\PayPeriod;
use App\Modules\HR\Enums\PayrollRunType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;

class PayrollPostingService
{
    public function __construct(
        protected PayrollService $payrollService,
        protected AccountMappingService $accountMappingService,
        protected JournalEntryService $journalEntryService,
        protected SequenceService $sequenceService
    ) {}

    /**
     * اعتماد مسير الرواتب وترحيله مالياً للمحاسبة
     */
    public function postPayrollBatch($employees, PayPeriod $period, PayrollRunType $runType, string $description): PayrollBatch
    {
        return DB::transaction(function () use ($employees, $period, $runType, $description) {
            $groupedDebits = [];
            $groupedCredits = [];
            $employeePayables = [];
            $totalNetSalaries = 0.0;

            $startDate = $period->start_date->format('Y-m-d');
            $endDate = $period->end_date->format('Y-m-d');

            // توليد الرقم التسلسلي للمسير
            $batchNumber = $this->sequenceService->generateNumber('hr_payroll_batch');

            // 1. إنشاء رأس المسير (Payroll Batch Header)
            $payrollBatch = PayrollBatch::create([
                'number'        => $batchNumber,
                'name'          => mb_substr($description, 0, 255),
                'pay_period_id' => $period->id,
                'run_type'      => $runType->value,
                'status'        => 'posted',
                'approved_at'   => now(),
                'approved_by'   => Auth::id(),
            ]);

            $rules = SalaryRule::all()->keyBy('code');

            // 2. التحقق الاستباقي من الربط المحاسبي لحساب الرواتب المستحقة
            $payableAccountId = $this->accountMappingService->getAccountId('hr_salaries_payable');
            if (!$payableAccountId) {
                throw new Exception("فشل الاعتماد: حساب الرواتب المستحقة (hr_salaries_payable) غير مربوط في شجرة الحسابات!");
            }

            foreach ($employees as $employee) {
                $costCenterId = $employee->department?->cost_center_id;

                // احتساب قسيمة راتب الموظف
                $payslipData = $this->payrollService->previewPayslip($employee, $period, $runType);

                // حفظ اللقطة النهائية للراتب (Snapshot)
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

                // 3. تجميع الحسابات المدينة والدائنة حسب التوجيه المحاسبي
                foreach ($payslipData['lines'] as $line) {
                    $code = $line['code'];
                    $amount = (float) $line['amount'];

                    if ($amount <= 0) {
                        continue;
                    }

                    $rule = $rules->get($code);
                    if (!$rule || !$rule->account_mapping_key) {
                        throw new Exception("قاعدة الراتب ({$code}) ليس لها مفتاح توجيه محاسبي!");
                    }

                    $accountId = $this->accountMappingService->getAccountId($rule->account_mapping_key);
                    if (!$accountId) {
                        throw new Exception("التوجيه المحاسبي ({$rule->account_mapping_key}) الخاص بالقاعدة ({$code}) غير مربوط بحساب مالي فعال!");
                    }

                    if ($line['category'] === 'allowance') {
                        $key = "{$accountId}_{$costCenterId}";
                        if (!isset($groupedDebits[$key])) {
                            $groupedDebits[$key] = [
                                'account_id'     => $accountId,
                                'cost_center_id' => $costCenterId,
                                'amount'         => 0.0,
                            ];
                        }
                        $groupedDebits[$key]['amount'] += $amount;
                    } elseif ($line['category'] === 'deduction') {
                        if (!isset($groupedCredits[$accountId])) {
                            $groupedCredits[$accountId] = 0.0;
                        }
                        $groupedCredits[$accountId] += $amount;
                    }
                }

                // إضافة قيد استحقاق الموظف كطرف دائن في حساب الرواتب المستحقة
                $employeePayables[] = new JournalEntryDetailDto(
                    account_id: $payableAccountId,
                    debit: 0,
                    credit: $payslipData['totals']['net_salary'],
                    description: "رواتب مستحقة - {$employee->full_name}",
                    party_type: 'employee',
                    party_id: (string) $employee->id,
                    cost_center_id: null
                );

                // 4. إغلاق أقساط السلف والمدخلات المالية المرتبطة بالفترة
                if ($runType === PayrollRunType::Regular) {
                    LoanInstallment::whereHas('loan', fn($q) => $q->where('employee_id', $employee->id))
                        ->where('status', 'pending')
                        ->whereBetween('due_month', [$startDate, $endDate])
                        ->update(['status' => 'deducted']);

                    PayrollInput::where('employee_id', $employee->id)
                        ->where('is_processed', false)
                        ->whereBetween('date', [$startDate, $endDate])
                        ->update([
                            'is_processed'     => true,
                            'payroll_batch_id' => $payrollBatch->id,
                        ]);
                }
            }

            // 5. بناء أطراف القيد المحاسبي المزدوج
            $journalDetails = [];

            // أطراف المدين (مصروفات الرواتب والبدلات حسب مراكز التكلفة)
            foreach ($groupedDebits as $debit) {
                $journalDetails[] = new JournalEntryDetailDto(
                    account_id: $debit['account_id'],
                    debit: $debit['amount'],
                    credit: 0,
                    cost_center_id: $debit['cost_center_id'],
                    description: "مصروفات مسير رواتب - {$description}"
                );
            }

            // أطراف الدائن (الاستقطاعات العامة والتأمينات)
            foreach ($groupedCredits as $accountId => $amount) {
                $journalDetails[] = new JournalEntryDetailDto(
                    account_id: $accountId,
                    debit: 0,
                    credit: $amount,
                    description: "استقطاعات مسير رواتب - {$description}"
                );
            }

            // أطراف الدائن (صافي رواتب الموظفين المستحقة)
            $journalDetails = array_merge($journalDetails, $employeePayables);

            // 6. إنشاء القيد المحاسبي عبر خدمة القيود
            $defaultCurrencyId = (int) config('accounting.default_currency_id', 1);

            $journalEntryDto = new JournalEntryDto(
                date: $endDate,
                details: $journalDetails,
                description: $description,
                currency_id: $defaultCurrencyId
            );

            $journalEntry = $this->journalEntryService->createEntry($journalEntryDto);

            // تمييز القيد المحاسبي بأنه ناتج عن مسير الرواتب وربطه بالدفعة
            $journalEntry->update([
                'source'         => 'hr_payroll',
                'reference_type' => PayrollBatch::class,
                'reference_id'   => $payrollBatch->id,
            ]);

            // ترحيل القيد فورياً إلى دفتر الأستاذ وتوليد رقمه الرسمي
            $journalEntry = $this->journalEntryService->postEntry($journalEntry);

            // ربط القيد بدفعة المسير
            $payrollBatch->update([
                'journal_entry_id' => $journalEntry->id,
            ]);

            // 7. إغلاق الفترة المالية بعد اكتمال الترحيل المنتظم
            if ($runType === PayrollRunType::Regular) {
                $period->update(['status' => 'closed']);
            }

            return $payrollBatch;
        });
    }

    /**
     * إلغاء والتراجع عن مسير الرواتب المعتمد وترحيل قيد عكسي
     */
    public function rollbackPayrollBatch(PayrollBatch $batch, string $reason): bool
    {
        return DB::transaction(function () use ($batch, $reason) {
            if ($batch->status !== 'posted') {
                throw new Exception("لا يمكن التراجع عن مسير غير معتمد أو ملغي مسبقاً.");
            }

            $period = $batch->payPeriod;
            if (!$period) {
                throw new Exception("الفترة المالية المرتبطة بهذا المسير غير موجودة.");
            }

            $startDate = $period->start_date->format('Y-m-d');
            $endDate = $period->end_date->format('Y-m-d');

            // 1. معالجة القيد المحاسبي المرتبط
            $originalEntry = $batch->journalEntry;
            if ($originalEntry) {
                if ($originalEntry->status === EntryStatus::Posted) {
                    $reversalDetails = [];
                    foreach ($originalEntry->details as $detail) {
                        $reversalDetails[] = new JournalEntryDetailDto(
                            account_id: $detail->account_id,
                            debit: (float) $detail->credit,
                            credit: (float) $detail->debit,
                            description: "قيد عكسي لمسير رواتب [{$batch->number}] - {$reason}",
                            party_type: $detail->party_type,
                            party_id: $detail->party_id ? (string) $detail->party_id : null,
                            cost_center_id: $detail->cost_center_id
                        );
                    }

                    $reversalDto = new JournalEntryDto(
                        date: now()->format('Y-m-d'),
                        details: $reversalDetails,
                        description: "قيد عكسي لإلغاء مسير الرواتب ({$batch->number}): {$reason}",
                        currency_id: $originalEntry->currency_id
                    );

                    $reversalEntry = $this->journalEntryService->createEntry($reversalDto);
                    $reversalEntry->update([
                        'source'         => 'hr_payroll_reversal',
                        'reference_type' => PayrollBatch::class,
                        'reference_id'   => $batch->id,
                    ]);

                    $this->journalEntryService->postEntry($reversalEntry);
                } elseif ($originalEntry->status === EntryStatus::Draft) {
                    $this->journalEntryService->deleteEntry($originalEntry);
                }
            }

            // 2. إعادة فتح الفترة المالية إذا كان المسير اعتيادياً
            if ($batch->run_type === PayrollRunType::Regular->value) {
                $period->update(['status' => 'open']);

                // 3. إعادة أقساط السلف والمدخلات المالية المعالجة إلى حالتها السابقة
                $employeeIds = $batch->payslips()->pluck('employee_id')->toArray();

                if (!empty($employeeIds)) {
                    LoanInstallment::whereHas('loan', fn($q) => $q->whereIn('employee_id', $employeeIds))
                        ->where('status', 'deducted')
                        ->whereBetween('due_month', [$startDate, $endDate])
                        ->update(['status' => 'pending']);
                }

                PayrollInput::where('payroll_batch_id', $batch->id)
                    ->update([
                        'is_processed'     => false,
                        'payroll_batch_id' => null,
                    ]);
            }

            // 4. تحديث حالة الدفعة والحذف الناعم
            $batch->update([
                'status' => 'cancelled',
            ]);

            $batch->delete();

            return true;
        });
    }
}