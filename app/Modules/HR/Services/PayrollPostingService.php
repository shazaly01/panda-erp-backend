<?php

declare(strict_types=1);

namespace App\Modules\HR\Services;

use App\Modules\Accounting\DTO\JournalEntryDetailDto;
use App\Modules\Accounting\DTO\JournalEntryDto;
use App\Modules\Accounting\Enums\EntryStatus;
use App\Modules\Accounting\Services\AccountMappingService;
use App\Modules\Accounting\Services\JournalEntryService;
use App\Modules\Core\Services\SequenceService;
use App\Modules\HR\Enums\EmployeeStatus;
use App\Modules\HR\Enums\PayrollRunType;
use App\Modules\HR\Enums\SalaryRuleCategory;
use App\Modules\HR\Models\LoanInstallment;
use App\Modules\HR\Models\PayPeriod;
use App\Modules\HR\Models\PayrollBatch;
use App\Modules\HR\Models\PayrollInput;
use App\Modules\HR\Models\Payslip;
use App\Modules\HR\Models\SalaryRule;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PayrollPostingService
{
    public function __construct(
        protected PayrollService $payrollService,
        protected ScheduleResolutionService $scheduleResolutionService,
        protected AccountMappingService $accountMappingService,
        protected JournalEntryService $journalEntryService,
        protected SequenceService $sequenceService
    ) {}

    /**
     * اعتماد مسير الرواتب أو تحديث مسير قائم وترحيله مالياً للمحاسبة
     */
    public function postPayrollBatch(
        $employees,
        PayPeriod $period,
        PayrollRunType $runType,
        string $description,
        ?int $editingBatchId = null,
        ?float $disbursedAmount = null,
        ?float $disbursementPercentage = null
    ): PayrollBatch {
        return DB::transaction(function () use (
            $employees,
            $period,
            $runType,
            $description,
            $editingBatchId,
            $disbursedAmount,
            $disbursementPercentage
        ): PayrollBatch {
            // 1. قفل سجل الفترة المالية لمنع التداخل المتزامن والتحقق من حالتها
            $lockedPeriod = PayPeriod::where('id', $period->id)->lockForUpdate()->firstOrFail();

            if ($editingBatchId === null && $lockedPeriod->status === 'closed') {
                throw new Exception("فشل الاعتماد: الفترة المالية ({$lockedPeriod->name}) مغلقة ولا يمكن إنشاء مسير جديد عليها.");
            }

            $employeeIds = $employees->pluck('id')->all();
            if (empty($employeeIds)) {
                throw new Exception("يجب اختيار موظف واحد على الأقل لاعتماد المسير.");
            }

            // 2. في حالة تعديل مسير قائم: التحقق من القفل الزمني وفك ربط الحركات السابقة وحذف التفاصيل القديمة
            $existingBatch = null;
            if ($editingBatchId !== null) {
                $existingBatch = PayrollBatch::with(['payPeriod', 'journalEntry'])
                    ->where('id', $editingBatchId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((int) $existingBatch->pay_period_id !== (int) $lockedPeriod->id || $existingBatch->run_type !== $runType->value) {
                    throw new Exception("فشل التحديث: بيانات الفترة أو نوع المسير لا تتطابق مع المسير المحدد للتعديل.");
                }

                if (!$existingBatch->isEditable()) {
                    throw new Exception("فشل التحديث: لا يمكن تعديل هذا المسير لوجود مسير أحدث معتمد أو لأنه ملغي.");
                }

                // فك ربط أقساط السلف والمدخلات المتغيرة المرتبطة بهذا المسير لإعادة احتسابها بدقة
                LoanInstallment::where('payroll_batch_id', $existingBatch->id)
                    ->where('status', 'deducted')
                    ->update([
                        'status'           => 'pending',
                        'payroll_batch_id' => null,
                    ]);

                PayrollInput::where('payroll_batch_id', $existingBatch->id)
                    ->update([
                        'is_processed'     => false,
                        'payroll_batch_id' => null,
                    ]);

                // حذف قسائم الرواتب القديمة نهائياً (Hard Delete) لإعادة تنزيلها محدثة
                $existingBatch->payslips()->delete();
            }

            // 3. الحماية من الترحيل المزدوج لنفس الموظف في مسيرات أخرى لنفس الفترة ونوع المسير
            $alreadyPostedEmployees = Payslip::with('employee:id,full_name')
                ->whereIn('employee_id', $employeeIds)
                ->whereHas('batch', function ($query) use ($lockedPeriod, $runType, $editingBatchId): void {
                    $query->where('status', 'posted')
                          ->where('pay_period_id', $lockedPeriod->id)
                          ->where('run_type', $runType->value);

                    if ($editingBatchId !== null) {
                        $query->where('id', '!=', $editingBatchId);
                    }
                })
                ->get();

            if ($alreadyPostedEmployees->isNotEmpty()) {
                $names = $alreadyPostedEmployees
                    ->map(fn (Payslip $slip) => $slip->employee?->full_name ?? "#{$slip->employee_id}")
                    ->unique()
                    ->implode('، ');

                throw new Exception("فشل الاعتماد: تم ترحيل رواتب الموظفين التاليين مسبقاً في مسير آخر لهذه الفترة ونوع المسير: ({$names}).");
            }

            // 4. التحقق الاستباقي من الربط المحاسبي لحساب الرواتب المستحقة
            $payableAccountId = $this->accountMappingService->getAccountId('hr_salaries_payable');
            if (!$payableAccountId) {
                throw new Exception("فشل الاعتماد: حساب الرواتب المستحقة (hr_salaries_payable) غير مربوط في شجرة الحسابات!");
            }

            $groupedDebits = [];
            $groupedCredits = [];
            $employeePayables = [];
            $computedPayslips = [];
            $totalBatchNet = 0.0;

            $startDate = $lockedPeriod->start_date->format('Y-m-d');
            $endDate = $lockedPeriod->end_date->format('Y-m-d');

            // التحميل المسبق لبيانات الجدولة والاستثناءات لتسريع المعالجة الجماعية
            $this->scheduleResolutionService->preloadForPeriod($employeeIds, $startDate, $endDate);

            // 5. إنشاء أو تحديث رأس المسير (Payroll Batch Header) مع الاحتفاظ بنفس الرقم التسلسلي
            if ($existingBatch !== null) {
                $existingBatch->update([
                    'name'        => mb_substr($description, 0, 255),
                    'status'      => 'posted',
                    'approved_at' => now(),
                    'approved_by' => Auth::id(),
                ]);
                $payrollBatch = $existingBatch;
            } else {
                $batchNumber = $this->sequenceService->generateNumber('hr_payroll_batch');
                $payrollBatch = PayrollBatch::create([
                    'number'                  => $batchNumber,
                    'name'                    => mb_substr($description, 0, 255),
                    'pay_period_id'           => $lockedPeriod->id,
                    'run_type'                => $runType->value,
                    'status'                  => 'posted',
                    'disbursed_amount'        => 0,
                    'disbursement_percentage' => 100.00,
                    'approved_at'             => now(),
                    'approved_by'             => Auth::id(),
                ]);
            }

            $rules = SalaryRule::all()->keyBy('code');

            foreach ($employees as $employee) {
                // التحقق من أهلية الموظف ومطابقة عقده لمجموعة دفع الفترة
                if (in_array($employee->status?->value ?? $employee->status, [
                    EmployeeStatus::Dismissed->value,
                    EmployeeStatus::EndOfService->value,
                ], true)) {
                    throw new Exception("لا يمكن ترحيل راتب الموظف ({$employee->full_name}) لأن حالته الوظيفية منتهية أو مفصول.");
                }

                $contract = $employee->contractForPeriod($lockedPeriod);
                if (!$contract) {
                    throw new Exception("الموظف ({$employee->full_name}) لا يملك عقداً نشطاً متوافقاً مع مجموعة دفع وتواريخ الفترة ({$lockedPeriod->name}).");
                }

                $costCenterId = $employee->department?->cost_center_id;

                // احتساب قسيمة راتب الموظف
                $payslipData = $this->payrollService->previewPayslip($employee, $lockedPeriod, $runType);
                $netSalary = round((float) $payslipData['totals']['net_salary'], 2);

                // منع اعتماد صافي راتب سالب
                if ($netSalary < 0) {
                    throw new Exception("فشل الاعتماد: صافي راتب الموظف ({$employee->full_name}) سالب ({$netSalary}). يرجى مراجعة الاستقطاعات أو تأجيل جزء منها.");
                }

                $totalBatchNet = round($totalBatchNet + $netSalary, 2);

                $computedPayslips[] = [
                    'employee_id'      => $employee->id,
                    'basic_salary'     => $payslipData['contract_basic'],
                    'total_allowances' => $payslipData['totals']['total_allowances'],
                    'total_deductions' => $payslipData['totals']['total_deductions'],
                    'net_salary'       => $netSalary,
                    'details'          => $payslipData['lines'],
                ];

                // 6. تجميع الحسابات المدينة والدائنة حسب التوجيه المحاسبي (إثبات 100% من الاستحقاق)
                foreach ($payslipData['lines'] as $line) {
                    $code = $line['code'];
                    $amount = round((float) $line['amount'], 2);

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

                    if ($line['category'] === SalaryRuleCategory::Allowance->value) {
                        $key = "{$accountId}_{$costCenterId}";
                        if (!isset($groupedDebits[$key])) {
                            $groupedDebits[$key] = [
                                'account_id'     => $accountId,
                                'cost_center_id' => $costCenterId,
                                'amount'         => 0.0,
                            ];
                        }
                        $groupedDebits[$key]['amount'] = round($groupedDebits[$key]['amount'] + $amount, 2);
                    } elseif ($line['category'] === SalaryRuleCategory::Deduction->value) {
                        if (!isset($groupedCredits[$accountId])) {
                            $groupedCredits[$accountId] = 0.0;
                        }
                        $groupedCredits[$accountId] = round($groupedCredits[$accountId] + $amount, 2);
                    } elseif ($line['category'] === SalaryRuleCategory::CompanyContribution->value) {
                        $debitKey = "{$accountId}_{$costCenterId}";
                        if (!isset($groupedDebits[$debitKey])) {
                            $groupedDebits[$debitKey] = [
                                'account_id'     => $accountId,
                                'cost_center_id' => $costCenterId,
                                'amount'         => 0.0,
                            ];
                        }
                        $groupedDebits[$debitKey]['amount'] = round($groupedDebits[$debitKey]['amount'] + $amount, 2);

                        if (!$rule->credit_account_mapping_key) {
                            throw new Exception("قاعدة مساهمة الشركة ({$code}) ليس لها مفتاح توجيه محاسبي للطرف الدائن!");
                        }

                        $creditAccountId = $this->accountMappingService->getAccountId($rule->credit_account_mapping_key);
                        if (!$creditAccountId) {
                            throw new Exception("التوجيه المحاسبي الدائن ({$rule->credit_account_mapping_key}) الخاص بقاعدة مساهمة الشركة ({$code}) غير مربوط بحساب مالي فعال!");
                        }

                        if (!isset($groupedCredits[$creditAccountId])) {
                            $groupedCredits[$creditAccountId] = 0.0;
                        }
                        $groupedCredits[$creditAccountId] = round($groupedCredits[$creditAccountId] + $amount, 2);
                    }
                }

                // إثبات كامل صافي الراتب كرصيد دائن في حساب الموظف (ليظل المتبقي غير المصروف مديونية له في ذمة الشركة)
                if ($netSalary > 0) {
                    $employeePayables[] = new JournalEntryDetailDto(
                        account_id: $payableAccountId,
                        debit: 0,
                        credit: $netSalary,
                        description: "رواتب مستحقة - {$employee->full_name}",
                        party_type: 'employee',
                        party_id: (string) $employee->id,
                        cost_center_id: null
                    );
                }

                // 7. إغلاق أقساط السلف المصروفة والمدخلات المالية المرتبطة بالفترة وربطها برقم المسير
                if ($runType === PayrollRunType::Regular) {
                    LoanInstallment::whereHas('loan', function ($q) use ($employee): void {
                            $q->where('employee_id', $employee->id)
                              ->where('status', 'paid_to_employee');
                        })
                        ->where('status', 'pending')
                        ->whereBetween('due_month', [$startDate, $endDate])
                        ->update([
                            'status'           => 'deducted',
                            'payroll_batch_id' => $payrollBatch->id,
                        ]);

                    PayrollInput::where('employee_id', $employee->id)
                        ->where('is_processed', false)
                        ->whereBetween('date', [$startDate, $endDate])
                        ->update([
                            'is_processed'     => true,
                            'payroll_batch_id' => $payrollBatch->id,
                        ]);
                }
            }

            // 8. حساب وتوزيع مبلغ الصرف الفعلي ونسبة الصرف على قسائم الموظفين مع جبر فروقات التقريب
            [$effectiveDisbursed, $effectivePercentage] = $this->resolveDisbursementTotals(
                $totalBatchNet,
                $disbursedAmount,
                $disbursementPercentage
            );

            $payrollBatch->update([
                'disbursed_amount'        => $effectiveDisbursed,
                'disbursement_percentage' => $effectivePercentage,
            ]);

            $this->persistDistributedPayslips($payrollBatch, $computedPayslips, $totalBatchNet, $effectiveDisbursed, $effectivePercentage);

            // 9. بناء أطراف القيد المحاسبي المزدوج
            $journalDetails = [];

            foreach ($groupedDebits as $debit) {
                $journalDetails[] = new JournalEntryDetailDto(
                    account_id: $debit['account_id'],
                    debit: $debit['amount'],
                    credit: 0,
                    cost_center_id: $debit['cost_center_id'],
                    description: "مصروفات مسير رواتب - {$description}"
                );
            }

            foreach ($groupedCredits as $accountId => $amount) {
                $journalDetails[] = new JournalEntryDetailDto(
                    account_id: $accountId,
                    debit: 0,
                    credit: $amount,
                    description: "استقطاعات والتزامات مسير رواتب - {$description}"
                );
            }

            $journalDetails = array_merge($journalDetails, $employeePayables);

            if (empty($journalDetails)) {
                throw new Exception("فشل الاعتماد: لا توجد أي مبالغ مالية مستحقة لإنشاء قيد محاسبي لهذا المسير.");
            }

            // 10. إنشاء القيد المحاسبي أو تحديث القيد القائم بنفس رقمه التسلسلي
            $defaultCurrencyId = (int) config('accounting.default_currency_id', 1);

            $journalEntryDto = new JournalEntryDto(
                date: $endDate,
                details: $journalDetails,
                description: $description,
                currency_id: $defaultCurrencyId
            );

            if ($existingBatch !== null && $existingBatch->journalEntry !== null) {
                $this->journalEntryService->updatePostedSystemEntry($existingBatch->journalEntry, $journalEntryDto);
            } else {
                $journalEntry = $this->journalEntryService->createEntry($journalEntryDto);

                $journalEntry->update([
                    'source'         => 'hr_payroll',
                    'reference_type' => PayrollBatch::class,
                    'reference_id'   => $payrollBatch->id,
                ]);

                $journalEntry = $this->journalEntryService->postEntry($journalEntry);

                $payrollBatch->update([
                    'journal_entry_id' => $journalEntry->id,
                ]);
            }

            // 11. إغلاق الفترة المالية تلقائياً عند اكتمال ترحيل كافة الموظفين المؤهلين في الفترة
            if ($runType === PayrollRunType::Regular) {
                $remainingEligibleCount = $this->payrollService
                    ->getEligibleEmployees($lockedPeriod, $runType)
                    ->where('is_processed', false)
                    ->count();

                if ($remainingEligibleCount === 0) {
                    $lockedPeriod->update(['status' => 'closed']);
                } else {
                    $lockedPeriod->update(['status' => 'open']);
                }
            }

            return $payrollBatch->refresh();
        });
    }

    /**
     * احتساب إجمالي المبلغ المصروف ونسبة الصرف المئوية المعتمدة للمسير
     *
     * @return array{0: float, 1: float}
     */
    protected function resolveDisbursementTotals(
        float $totalBatchNet,
        ?float $disbursedAmount,
        ?float $disbursementPercentage
    ): array {
        if ($totalBatchNet <= 0) {
            return [0.0, 100.00];
        }

        if ($disbursedAmount !== null) {
            $clampedAmount = max(0.0, min($totalBatchNet, round($disbursedAmount, 2)));
            $percentage = round(($clampedAmount / $totalBatchNet) * 100, 2);

            return [$clampedAmount, $percentage];
        }

        if ($disbursementPercentage !== null) {
            $clampedPercentage = max(0.0, min(100.0, round($disbursementPercentage, 2)));
            $amount = round($totalBatchNet * ($clampedPercentage / 100.0), 2);

            return [$amount, $clampedPercentage];
        }

        return [$totalBatchNet, 100.00];
    }

    /**
     * حفظ قسائم الرواتب وتوزيع المبلغ المستلم نسبياً على الموظفين مع جبر فروقات التقريب
     */
    protected function persistDistributedPayslips(
        PayrollBatch $payrollBatch,
        array $computedPayslips,
        float $totalBatchNet,
        float $effectiveDisbursed,
        float $effectivePercentage
    ): void {
        $lastPositiveIndex = null;
        foreach ($computedPayslips as $idx => $slip) {
            if ((float) $slip['net_salary'] > 0) {
                $lastPositiveIndex = $idx;
            }
        }

        $distributedSum = 0.0;
        $ratio = ($totalBatchNet > 0) ? ($effectiveDisbursed / $totalBatchNet) : 0.0;

        foreach ($computedPayslips as $idx => $slip) {
            $netSalary = (float) $slip['net_salary'];

            if ($netSalary <= 0 || $effectiveDisbursed <= 0 || $effectivePercentage <= 0) {
                $paidAmount = 0.0;
            } elseif ($effectivePercentage >= 100.0 || $effectiveDisbursed >= $totalBatchNet) {
                $paidAmount = $netSalary;
            } elseif ($idx === $lastPositiveIndex) {
                $paidAmount = round(max(0.0, min($netSalary, $effectiveDisbursed - $distributedSum)), 2);
            } else {
                $paidAmount = round(min($netSalary, $netSalary * $ratio), 2);
                $distributedSum = round($distributedSum + $paidAmount, 2);
            }

            $remainingAmount = round(max(0.0, $netSalary - $paidAmount), 2);

            Payslip::create([
                'payroll_batch_id' => $payrollBatch->id,
                'employee_id'      => $slip['employee_id'],
                'basic_salary'     => $slip['basic_salary'],
                'total_allowances' => $slip['total_allowances'],
                'total_deductions' => $slip['total_deductions'],
                'net_salary'       => $netSalary,
                'paid_amount'      => $paidAmount,
                'remaining_amount' => $remainingAmount,
                'details'          => $slip['details'],
            ]);
        }
    }

    /**
     * إلغاء والتراجع عن مسير الرواتب المعتمد وترحيل قيد عكسي مع إبقاء السجل للتدقيق
     */
    public function rollbackPayrollBatch(PayrollBatch $batch, string $reason): bool
    {
        return DB::transaction(function () use ($batch, $reason): bool {
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

            // 2. إعادة فتح الفترة المالية واسترجاع الأقساط والمدخلات المرتبطة بالمسير
            if ($batch->run_type === PayrollRunType::Regular->value) {
                $period->update(['status' => 'open']);

                $updatedByBatch = LoanInstallment::where('payroll_batch_id', $batch->id)
                    ->where('status', 'deducted')
                    ->update([
                        'status'           => 'pending',
                        'payroll_batch_id' => null,
                    ]);

                if ($updatedByBatch === 0) {
                    $employeeIds = $batch->payslips()->pluck('employee_id')->toArray();
                    if (!empty($employeeIds)) {
                        LoanInstallment::whereHas('loan', fn ($q) => $q->whereIn('employee_id', $employeeIds))
                            ->whereNull('payroll_batch_id')
                            ->where('status', 'deducted')
                            ->whereBetween('due_month', [$startDate, $endDate])
                            ->update(['status' => 'pending']);
                    }
                }

                PayrollInput::where('payroll_batch_id', $batch->id)
                    ->update([
                        'is_processed'     => false,
                        'payroll_batch_id' => null,
                    ]);
            }

            // 3. تحديث حالة الدفعة إلى ملغاة دون حذفها لإبقائها في السجل التدقيقي
            $batch->update([
                'status' => 'cancelled',
            ]);

            return true;
        });
    }
}