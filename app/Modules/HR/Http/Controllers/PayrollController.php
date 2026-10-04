<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\PayPeriod;
use App\Modules\HR\Models\PayrollBatch;
use App\Modules\HR\Models\Payslip;
use App\Modules\HR\Services\PayrollService;
use App\Modules\HR\Services\PayrollPostingService;
use App\Modules\HR\Enums\PayrollRunType;
use App\Modules\HR\Policies\PayrollPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Modules\HR\Http\Requests\Payroll\GetEligibleEmployeesRequest;
use App\Modules\HR\Http\Resources\EligiblePayrollEmployeeResource;
use Exception;

class PayrollController extends Controller
{
    public function __construct(
        private readonly PayrollService $payrollService,
        private readonly PayrollPostingService $payrollPostingService
    ) {
        $this->middleware('auth:sanctum');
    }

    /**
     * معاينة قسيمة راتب (قبل الاعتماد والحفظ)
     */
    public function preview(Request $request): JsonResponse
    {
        $this->authorize('preview', PayrollPolicy::class);

        $request->validate([
            'employee_id'   => 'required|exists:employees,id',
            'pay_period_id' => 'required|exists:hr_pay_periods,id',
            'run_type'      => 'required|in:regular,overtime_only',
        ]);

        try {
            $employee = Employee::with([
                'department',
                'activeContract.salaryStructure.rules',
                'activeContract.overtimePolicy',
                'currentContract.salaryStructure.rules',
                'currentContract.overtimePolicy',
            ])->findOrFail($request->employee_id);

            $period = PayPeriod::findOrFail($request->pay_period_id);
            $runType = PayrollRunType::from($request->run_type);

            $payslipData = $this->payrollService->previewPayslip($employee, $period, $runType);

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
     * اعتماد الرواتب وترحيلها للحسابات
     */
    public function postBatch(Request $request): JsonResponse
    {
        $this->authorize('postBatch', PayrollPolicy::class);

        $request->validate([
            'employee_ids'   => 'required|array|min:1',
            'employee_ids.*' => 'exists:employees,id',
            'pay_period_id'  => 'required|exists:hr_pay_periods,id',
            'run_type'       => 'required|in:regular,overtime_only',
            'description'    => 'required|string|max:255',
        ]);

        try {
            $employees = Employee::with([
                'department',
                'activeContract.salaryStructure.rules',
                'activeContract.overtimePolicy',
                'currentContract.salaryStructure.rules',
                'currentContract.overtimePolicy',
            ])->whereIn('id', $request->employee_ids)->get();

            $period = PayPeriod::findOrFail($request->pay_period_id);
            $runType = PayrollRunType::from($request->run_type);

            $batch = $this->payrollPostingService->postPayrollBatch(
                $employees,
                $period,
                $runType,
                $request->description
            );

            return response()->json([
                'message'  => 'تم اعتماد الرواتب وترحيلها بنجاح.',
                'batch_id' => $batch->id,
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
    public function rollbackBatch(Request $request, int|string $batchId): JsonResponse
    {
        $this->authorize('rollback', PayrollPolicy::class);

        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        try {
            $batch = PayrollBatch::with([
                'payPeriod',
                'journalEntry.details',
                'payslips',
            ])->findOrFail($batchId);

            $this->payrollPostingService->rollbackPayrollBatch($batch, $request->reason);

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
     * جلب سجل المسيرات السابقة المعتمدة مع إجمالي مبالغها وعدد موظفيها
     */
    public function getBatches(): JsonResponse
    {
        $this->authorize('view', PayrollPolicy::class);

        $batches = PayrollBatch::with([
                'creator:id,name',
                'journalEntry:id,entry_number',
                'payPeriod:id,name,start_date,end_date',
            ])
            ->withSum('payslips as total_amount', 'net_salary')
            ->withCount('payslips as employees_count')
            ->latest('id')
            ->paginate(15);

        return response()->json($batches);
    }

    /**
     * حساب ملخص المسير (للواجهة الأمامية)
     */
    public function getSummary(Request $request): JsonResponse
    {
        $this->authorize('view', PayrollPolicy::class);

        $request->validate([
            'employee_ids'   => 'required|array|min:1',
            'employee_ids.*' => 'exists:employees,id',
            'pay_period_id'  => 'required|exists:hr_pay_periods,id',
            'run_type'       => 'required|in:regular,overtime_only',
        ]);

        $employees = Employee::with([
            'department',
            'activeContract.salaryStructure.rules',
            'activeContract.overtimePolicy',
            'currentContract.salaryStructure.rules',
            'currentContract.overtimePolicy',
        ])->whereIn('id', $request->employee_ids)->get();

        $period = PayPeriod::findOrFail($request->pay_period_id);
        $runType = PayrollRunType::from($request->run_type);

        $summary = [
            'total_basic'      => 0.0,
            'total_allowances' => 0.0,
            'total_deductions' => 0.0,
            'total_net'        => 0.0,
            'employee_count'   => $employees->count(),
        ];

        foreach ($employees as $employee) {
            $payslip = $this->payrollService->previewPayslip($employee, $period, $runType);

            $summary['total_basic']      += (float) $payslip['contract_basic'];
            $summary['total_allowances'] += (float) $payslip['totals']['total_allowances'];
            $summary['total_deductions'] += (float) $payslip['totals']['total_deductions'];
            $summary['total_net']        += (float) $payslip['totals']['net_salary'];
        }

        return response()->json([
            'message' => 'تم حساب ملخص الفترة بنجاح',
            'data'    => [
                'total_basic'      => round($summary['total_basic'], 2),
                'total_allowances' => round($summary['total_allowances'], 2),
                'total_deductions' => round($summary['total_deductions'], 2),
                'total_net'        => round($summary['total_net'], 2),
                'employee_count'   => $summary['employee_count'],
            ],
        ]);
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

        $processedEmployeeIds = Payslip::whereHas('batch', function ($query) use ($request) {
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
     * تصدير ملف تحويل الرواتب للبنك بصيغة CSV
     */
    public function exportBankFile(int|string $batchId): StreamedResponse
    {
        $this->authorize('view', Employee::class);

        $batch = PayrollBatch::with([
            'payslips.employee.primaryBankAccount',
        ])->findOrFail($batchId);

        $fileName = "Bank_Transfer_Batch_{$batchId}_" . date('Ymd_His') . ".csv";

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $columns = ['اسم الموظف', 'الرقم الوظيفي', 'اسم البنك', 'رقم الحساب', 'الآيبان (IBAN)', 'المبلغ الصافي'];

        $callback = function () use ($batch, $columns): void {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF"); // UTF-8 BOM لدعم اللغة العربية في Excel
            fputcsv($file, $columns);

            foreach ($batch->payslips as $payslip) {
                $employee = $payslip->employee;
                $bankAccount = $employee?->primaryBankAccount;

                fputcsv($file, [
                    $employee?->full_name ?? '---',
                    $employee?->employee_number ?? '---',
                    $bankAccount?->bank_name ?? 'لم يتم تحديد بنك',
                    $bankAccount?->account_number ?? '---',
                    $bankAccount?->iban ?? '---',
                    number_format((float) $payslip->net_salary, 2, '.', ''),
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

        $employees = $this->payrollService->getEligibleEmployees($period, $runType);

        return response()->json([
            'success' => true,
            'message' => 'تم جلب الموظفين المؤهلين للفترة بنجاح',
            'data'    => EligiblePayrollEmployeeResource::collection($employees),
        ]);
    }
}