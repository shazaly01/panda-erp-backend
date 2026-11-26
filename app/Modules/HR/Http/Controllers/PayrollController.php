<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Enums\PayrollRunType;
use App\Modules\HR\Http\Requests\Payroll\GetEligibleEmployeesRequest;
use App\Modules\HR\Http\Requests\Payroll\PayrollSummaryRequest;
use App\Modules\HR\Http\Requests\Payroll\PostPayrollBatchRequest;
use App\Modules\HR\Http\Requests\Payroll\PreviewPayrollRequest;
use App\Modules\HR\Http\Requests\Payroll\RollbackPayrollBatchRequest;
use App\Modules\HR\Http\Resources\EligiblePayrollEmployeeResource;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\PayGroup;
use App\Modules\HR\Models\PayPeriod;
use App\Modules\HR\Models\PayrollBatch;
use App\Modules\HR\Models\Payslip;
use App\Modules\HR\Policies\PayrollPolicy;
use App\Modules\HR\Services\PayPeriodGeneratorService;
use App\Modules\HR\Services\PayrollPostingService;
use App\Modules\HR\Services\PayrollService;
use App\Modules\HR\Services\ScheduleResolutionService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollController extends Controller
{
    public function __construct(
        private readonly PayrollService $payrollService,
        private readonly PayrollPostingService $payrollPostingService,
        private readonly ScheduleResolutionService $scheduleResolutionService,
        private readonly PayPeriodGeneratorService $payPeriodGeneratorService
    ) {
        $this->middleware('auth:sanctum');
    }

    /**
     * العلاقات القياسية المطلوبة لاحتساب رواتب الموظفين دون استعلامات متكررة
     */
    private function employeePayrollRelations(): array
    {
        return [
            'department',
            'contracts.salaryStructure.rules',
            'contracts.overtimePolicy',
            'contracts.workingSchedule.lines.shift',
            'activeContract.salaryStructure.rules',
            'activeContract.overtimePolicy',
            'currentContract.salaryStructure.rules',
            'currentContract.overtimePolicy',
        ];
    }

    /**
     * التهيئة التلقائية الذكية لمركز مسيرات الرواتب:
     * يحدد مجموعة الدفع النشطة وفترة الشهر الحالي تلقائياً (ويولدها إن لم تكن موجودة)،
     * ويجلب المسير القائم للشهر الحالي أو المسير المطلوب تعديله مع الموظفين المؤهلين مرتبين أبجدياً.
     */
    public function initialize(Request $request): JsonResponse
    {
        $this->authorize('view', PayrollPolicy::class);

        $request->validate([
            'pay_group_id'  => ['nullable', 'integer', 'exists:hr_pay_groups,id'],
            'pay_period_id' => ['nullable', 'integer', 'exists:hr_pay_periods,id'],
            'run_type'      => ['nullable', 'string', 'in:regular,overtime_only'],
            'batch_id'      => ['nullable', 'integer', 'exists:payroll_batches,id'],
        ]);

        try {
            $activeGroups = PayGroup::where('is_active', true)
                ->orderBy('id')
                ->get();

            $activeBatchModel = null;
            $selectedGroup = null;
            $selectedPeriod = null;
            $runType = PayrollRunType::from($request->input('run_type', PayrollRunType::Regular->value));

            // الحالة 1: فتح مسير محدد للتعديل (مثلاً من سجل المسيرات)
            if ($request->filled('batch_id')) {
                $activeBatchModel = PayrollBatch::with([
                    'payPeriod.payGroup',
                    'journalEntry:id,entry_number',
                    'payslips:id,payroll_batch_id,employee_id,basic_salary,total_allowances,total_deductions,net_salary,paid_amount,remaining_amount',
                ])->findOrFail((int) $request->input('batch_id'));

                $selectedPeriod = $activeBatchModel->payPeriod;
                $selectedGroup = $selectedPeriod?->payGroup;
                $runType = PayrollRunType::from($activeBatchModel->run_type);
            } else {
                // الحالة 2: التحديد التلقائي لمجموعة الدفع وفترة الشهر الحالي
                if ($request->filled('pay_group_id')) {
                    $selectedGroup = PayGroup::findOrFail((int) $request->input('pay_group_id'));
                } else {
                    $selectedGroup = $activeGroups->first();
                }

                if (!$selectedGroup) {
                    return response()->json([
                        'data' => [
                            'groups'                => [],
                            'periods'               => [],
                            'selected_group_id'     => null,
                            'selected_period'       => null,
                            'run_type'              => $runType->value,
                            'active_batch'          => null,
                            'employees'             => [],
                            'selected_employee_ids' => [],
                        ],
                    ]);
                }

                if ($request->filled('pay_period_id')) {
                    $selectedPeriod = PayPeriod::where('pay_group_id', $selectedGroup->id)
                        ->findOrFail((int) $request->input('pay_period_id'));
                } else {
                    $today = now()->toDateString();
                    $selectedPeriod = PayPeriod::where('pay_group_id', $selectedGroup->id)
                        ->where('start_date', '<=', $today)
                        ->where('end_date', '>=', $today)
                        ->first();

                    // إذا لم تكن فترة الشهر الحالي مولدة، نولد فترات السنة تلقائياً
                    if (!$selectedPeriod) {
                        try {
                            $this->payPeriodGeneratorService->generate($selectedGroup, (int) now()->year);
                        } catch (Exception) {
                            // تجاهل إذا كانت الفترات مولدة مسبقاً بتواريخ مختلفة
                        }

                        $selectedPeriod = PayPeriod::where('pay_group_id', $selectedGroup->id)
                            ->where('start_date', '<=', $today)
                            ->where('end_date', '>=', $today)
                            ->first()
                            ?? PayPeriod::where('pay_group_id', $selectedGroup->id)
                                ->orderBy('start_date', 'desc')
                                ->first();
                    }
                }

                // البحث عن مسير معتمد قائم لنفس الفترة ونوع المسير لعرضه مباشرة في وضع التعديل
                if ($selectedPeriod) {
                    $activeBatchModel = PayrollBatch::with([
                        'payPeriod',
                        'journalEntry:id,entry_number',
                        'payslips:id,payroll_batch_id,employee_id,basic_salary,total_allowances,total_deductions,net_salary,paid_amount,remaining_amount',
                    ])
                        ->where('pay_period_id', $selectedPeriod->id)
                        ->where('run_type', $runType->value)
                        ->where('status', 'posted')
                        ->latest('id')
                        ->first();
                }
            }

            $formatPeriod = static fn (PayPeriod $p): array => [
                'id'           => $p->id,
                'pay_group_id' => $p->pay_group_id,
                'name'         => $p->name,
                'start_date'   => $p->start_date?->format('Y-m-d'),
                'end_date'     => $p->end_date?->format('Y-m-d'),
                'status'       => $p->status,
            ];

            $periods = $selectedGroup
                ? PayPeriod::where('pay_group_id', $selectedGroup->id)
                    ->orderBy('start_date', 'desc')
                    ->get()
                    ->map($formatPeriod)
                    ->values()
                : collect();

            $employees = collect();
            $selectedEmployeeIds = [];
            $activeBatchPayload = null;

            if ($selectedPeriod) {
                $editingBatchId = ($activeBatchModel && $activeBatchModel->isEditable())
                    ? (int) $activeBatchModel->id
                    : null;

                $employees = $this->payrollService->getEligibleEmployees($selectedPeriod, $runType, $editingBatchId);

                if ($activeBatchModel) {
                    $payslipsByEmployee = $activeBatchModel->payslips->keyBy('employee_id');
                    $selectedEmployeeIds = $employees
                        ->filter(fn (Employee $emp): bool => $payslipsByEmployee->has($emp->id))
                        ->pluck('id')
                        ->map(fn ($id) => (int) $id)
                        ->values()
                        ->all();

                    $activeBatchPayload = [
                        'id'                      => $activeBatchModel->id,
                        'number'                  => $activeBatchModel->number,
                        'name'                    => $activeBatchModel->name,
                        'pay_period_id'           => $activeBatchModel->pay_period_id,
                        'run_type'                => $activeBatchModel->run_type,
                        'status'                  => $activeBatchModel->status,
                        'disbursed_amount'        => (float) $activeBatchModel->disbursed_amount,
                        'disbursement_percentage' => (float) $activeBatchModel->disbursement_percentage,
                        'can_edit'                => $activeBatchModel->isEditable(),
                        'journal_entry_number'    => $activeBatchModel->journalEntry?->entry_number,
                        'payslips'                => $payslipsByEmployee->map(fn (Payslip $slip): array => [
                            'employee_id'      => (int) $slip->employee_id,
                            'basic_salary'     => (float) $slip->basic_salary,
                            'total_allowances' => (float) $slip->total_allowances,
                            'total_deductions' => (float) $slip->total_deductions,
                            'net_salary'       => (float) $slip->net_salary,
                            'paid_amount'      => (float) $slip->paid_amount,
                            'remaining_amount' => (float) $slip->remaining_amount,
                        ])->all(),
                    ];
                } else {
                    $selectedEmployeeIds = $employees
                        ->filter(fn (Employee $emp): bool => !$emp->is_processed)
                        ->pluck('id')
                        ->map(fn ($id) => (int) $id)
                        ->values()
                        ->all();
                }
            }

            return response()->json([
                'data' => [
                    'groups'                => $activeGroups,
                    'periods'               => $periods,
                    'selected_group_id'     => $selectedGroup?->id,
                    'selected_period'       => $selectedPeriod ? $formatPeriod($selectedPeriod) : null,
                    'run_type'              => $runType->value,
                    'active_batch'          => $activeBatchPayload,
                    'employees'             => EligiblePayrollEmployeeResource::collection($employees)->resolve(),
                    'selected_employee_ids' => $selectedEmployeeIds,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'تعذر تهيئة بيانات مسير الرواتب',
                'error'   => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * معاينة قسيمة راتب (قبل الاعتماد والحفظ أو أثناء التعديل)
     */
    public function preview(PreviewPayrollRequest $request): JsonResponse
    {
        $this->authorize('preview', PayrollPolicy::class);

        try {
            $validated = $request->validated();

            $employee = Employee::with($this->employeePayrollRelations())
                ->findOrFail($validated['employee_id']);

            $period = PayPeriod::findOrFail($validated['pay_period_id']);
            $runType = PayrollRunType::from($validated['run_type']);
            $editingBatchId = isset($validated['batch_id']) ? (int) $validated['batch_id'] : null;

            $payslipData = $this->payrollService->previewPayslip($employee, $period, $runType, $editingBatchId);

            return response()->json([
                'message' => 'تم احتساب معاينة الراتب للفترة بنجاح',
                'data'    => $payslipData,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'خطأ في احتساب الراتب',
                'error'   => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * اعتماد الرواتب أو تحديث مسير قائم وترحيله للحسابات (مرتباً أبجدياً لمطابقة توزيع الصرف)
     */
    public function postBatch(PostPayrollBatchRequest $request): JsonResponse
    {
        $this->authorize('postBatch', PayrollPolicy::class);

        try {
            $validated = $request->validated();

            $employees = Employee::with($this->employeePayrollRelations())
                ->whereIn('id', $validated['employee_ids'])
                ->orderBy('full_name', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $period = PayPeriod::findOrFail($validated['pay_period_id']);
            $runType = PayrollRunType::from($validated['run_type']);
            $editingBatchId = isset($validated['batch_id']) ? (int) $validated['batch_id'] : null;
            $disbursedAmount = array_key_exists('disbursed_amount', $validated) && $validated['disbursed_amount'] !== null
                ? (float) $validated['disbursed_amount']
                : null;
            $disbursementPercentage = array_key_exists('disbursement_percentage', $validated) && $validated['disbursement_percentage'] !== null
                ? (float) $validated['disbursement_percentage']
                : null;

            $batch = $this->payrollPostingService->postPayrollBatch(
                $employees,
                $period,
                $runType,
                $validated['description'],
                $editingBatchId,
                $disbursedAmount,
                $disbursementPercentage
            );

            $message = $editingBatchId !== null
                ? 'تم تحديث مسير الرواتب والقيد المحاسبي بنجاح.'
                : 'تم اعتماد الرواتب وترحيلها بنجاح.';

            return response()->json([
                'message'      => $message,
                'batch_id'     => $batch->id,
                'batch_number' => $batch->number,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'فشلت عملية الترحيل',
                'error'   => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * إلغاء والتراجع عن مسير الرواتب المعتمد وعكس القيد المحاسبي
     */
    public function rollbackBatch(RollbackPayrollBatchRequest $request, int|string $batchId): JsonResponse
    {
        $this->authorize('rollback', PayrollPolicy::class);

        try {
            $batch = PayrollBatch::with([
                'payPeriod',
                'journalEntry.details',
                'payslips',
            ])->findOrFail($batchId);

            $this->payrollPostingService->rollbackPayrollBatch(
                $batch,
                $request->validated('reason')
            );

            return response()->json([
                'message' => 'تم التراجع عن مسير الرواتب بنجاح، وعكس القيد المحاسبي وإعادة فتح الفترة.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'فشلت عملية التراجع عن المسير',
                'error'   => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * جلب سجل المسيرات السابقة (المعتمدة والملغاة) مع إجمالي مبالغها وعدد موظفيها وحالة قابلية التعديل
     */
    public function getBatches(): JsonResponse
    {
        $this->authorize('view', PayrollPolicy::class);

        $batches = PayrollBatch::with([
                'creator:id,name',
                'journalEntry:id,entry_number',
                'payPeriod:id,pay_group_id,name,start_date,end_date',
            ])
            ->withSum('payslips as total_amount', 'net_salary')
            ->withSum('payslips as total_remaining', 'remaining_amount')
            ->withCount('payslips as employees_count')
            ->latest('id')
            ->paginate(15);

        $batches->getCollection()->transform(function (PayrollBatch $batch): PayrollBatch {
            $batch->setAttribute('can_edit', $batch->isEditable());
            return $batch;
        });

        return response()->json($batches);
    }

    /**
     * حساب ملخص المسير (للواجهة الأمامية)
     */
    public function getSummary(PayrollSummaryRequest $request): JsonResponse
    {
        $this->authorize('view', PayrollPolicy::class);

        try {
            $validated = $request->validated();

            $employees = Employee::with($this->employeePayrollRelations())
                ->whereIn('id', $validated['employee_ids'])
                ->orderBy('full_name', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $period = PayPeriod::findOrFail($validated['pay_period_id']);
            $runType = PayrollRunType::from($validated['run_type']);
            $editingBatchId = isset($validated['batch_id']) ? (int) $validated['batch_id'] : null;

            $this->scheduleResolutionService->preloadForPeriod(
                $employees->pluck('id')->all(),
                $period->start_date->format('Y-m-d'),
                $period->end_date->format('Y-m-d')
            );

            $summary = [
                'total_basic'      => 0.0,
                'total_allowances' => 0.0,
                'total_deductions' => 0.0,
                'total_net'        => 0.0,
                'employee_count'   => $employees->count(),
                'items'            => [],
            ];

            foreach ($employees as $employee) {
                $payslip = $this->payrollService->previewPayslip($employee, $period, $runType, $editingBatchId);
                $netSalary = (float) $payslip['totals']['net_salary'];

                $summary['total_basic']      = round($summary['total_basic'] + (float) $payslip['contract_basic'], 2);
                $summary['total_allowances'] = round($summary['total_allowances'] + (float) $payslip['totals']['total_allowances'], 2);
                $summary['total_deductions'] = round($summary['total_deductions'] + (float) $payslip['totals']['total_deductions'], 2);
                $summary['total_net']        = round($summary['total_net'] + $netSalary, 2);

                $summary['items'][$employee->id] = [
                    'basic_salary'     => (float) $payslip['contract_basic'],
                    'total_allowances' => (float) $payslip['totals']['total_allowances'],
                    'total_deductions' => (float) $payslip['totals']['total_deductions'],
                    'net_salary'       => $netSalary,
                ];
            }

            return response()->json([
                'message' => 'تم حساب ملخص الفترة بنجاح',
                'data'    => $summary,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'خطأ في احتساب ملخص المسير',
                'error'   => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * جلب أرقام الموظفين الذين تم ترحيل رواتبهم لفترة محددة
     */
    public function getProcessedEmployees(Request $request): JsonResponse
    {
        $this->authorize('view', PayrollPolicy::class);

        $request->validate([
            'pay_period_id' => 'required|exists:hr_pay_periods,id',
            'run_type'      => 'required|in:regular,overtime_only',
        ]);

        $processedEmployeeIds = Payslip::whereHas('batch', function ($query) use ($request): void {
            $query->where('status', 'posted')
                  ->where('pay_period_id', $request->pay_period_id)
                  ->where('run_type', $request->run_type);
        })
        ->distinct()
        ->pluck('employee_id')
        ->toArray();

        return response()->json([
            'data' => $processedEmployeeIds,
        ]);
    }

    /**
     * تصدير ملف تحويل الرواتب للبنك بصيغة CSV مرتباً أبجدياً وفق المبلغ المقرر صرفه فعلياً (paid_amount)
     */
    public function exportBankFile(int|string $batchId): StreamedResponse|JsonResponse
    {
        $this->authorize('view', PayrollPolicy::class);

        $batch = PayrollBatch::with([
            'payslips.employee.primaryBankAccount',
        ])->findOrFail($batchId);

        if ((float) $batch->disbursed_amount <= 0) {
            return response()->json([
                'message' => 'لا يوجد مبلغ مصروف في هذا المسير لتصدير ملف البنك (نسبة الصرف 0%).',
            ], 422);
        }

        $fileName = "Bank_Transfer_Batch_{$batchId}_" . date('Ymd_His') . ".csv";

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $columns = [
            'اسم الموظف',
            'الرقم الوظيفي',
            'اسم البنك',
            'رقم الحساب',
            'الآيبان (IBAN)',
            'المبلغ المحول (المصروف)',
            'إجمالي صافي الراتب',
            'المتبقي (مديونية للموظف)',
        ];

        $sortedPayslips = $batch->payslips->sortBy(
            static fn (Payslip $p): string => (string) ($p->employee?->full_name ?? '')
        );

        $callback = function () use ($sortedPayslips, $columns): void {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF");
            fputcsv($file, $columns);

            foreach ($sortedPayslips as $payslip) {
                $paidAmount = (float) $payslip->paid_amount;
                if ($paidAmount <= 0) {
                    continue;
                }

                $employee = $payslip->employee;
                $bankAccount = $employee?->primaryBankAccount;

                fputcsv($file, [
                    $employee?->full_name ?? '---',
                    $employee?->employee_number ?? '---',
                    $bankAccount?->bank_name ?? 'لم يتم تحديد بنك',
                    $bankAccount?->account_number ?? '---',
                    $bankAccount?->iban ?? '---',
                    number_format($paidAmount, 2, '.', ''),
                    number_format((float) $payslip->net_salary, 2, '.', ''),
                    number_format((float) $payslip->remaining_amount, 2, '.', ''),
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * جلب قائمة الموظفين المؤهلين لمسير الفترة المالية المحددة
     */
    public function getEligibleEmployees(GetEligibleEmployeesRequest $request): JsonResponse
    {
        $period = PayPeriod::findOrFail($request->validated('pay_period_id'));
        $runType = PayrollRunType::from($request->validated('run_type'));
        $editingBatchId = $request->filled('batch_id') ? (int) $request->validated('batch_id') : null;

        $employees = $this->payrollService->getEligibleEmployees($period, $runType, $editingBatchId);

        return response()->json([
            'success' => true,
            'message' => 'تم جلب الموظفين المؤهلين للفترة بنجاح',
            'data'    => EligiblePayrollEmployeeResource::collection($employees),
        ]);
    }
}