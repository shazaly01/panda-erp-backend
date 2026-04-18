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
use Illuminate\Support\Facades\DB;
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
            $employee = Employee::findOrFail($request->employee_id);
            $period = PayPeriod::findOrFail($request->pay_period_id);
            $runType = PayrollRunType::from($request->run_type);

            $payslipData = $this->payrollService->previewPayslip($employee, $period, $runType);

            return response()->json([
                'message' => "تم احتساب معاينة الراتب للفترة بنجاح",
                'data' => $payslipData
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'خطأ في احتساب الراتب',
                'error' => $e->getMessage()
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
            'employee_ids'   => 'required|array',
            'employee_ids.*' => 'exists:employees,id',
            'pay_period_id'  => 'required|exists:hr_pay_periods,id',
            'run_type'       => 'required|in:regular,overtime_only',
            'description'    => 'required|string',
        ]);

        try {
            DB::beginTransaction();

            $employees = Employee::whereIn('id', $request->employee_ids)->get();
            $period = PayPeriod::findOrFail($request->pay_period_id);
            $runType = PayrollRunType::from($request->run_type);

            $this->payrollPostingService->postPayrollBatch(
                $employees,
                $period,
                $runType,
                $request->description
            );

            DB::commit();
            return response()->json(['message' => 'تم اعتماد الرواتب بنجاح.']);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'فشلت عملية الترحيل', 'error' => $e->getMessage()], 422);
        }
    }

    public function getBatches(): JsonResponse
    {
        $this->authorize('view', PayrollPolicy::class);

        // تم التحديث لقراءة علاقة payPeriod بدلاً من start_date
        $batches = PayrollBatch::with(['creator:id,name', 'journalEntry:id,entry_number', 'payPeriod:id,name,start_date,end_date'])
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
            'employee_ids'   => 'required|array',
            'employee_ids.*' => 'exists:employees,id',
            'pay_period_id'  => 'required|exists:hr_pay_periods,id',
            'run_type'       => 'required|in:regular,overtime_only',
        ]);

        $employees = Employee::whereIn('id', $request->employee_ids)->get();
        $period = PayPeriod::findOrFail($request->pay_period_id);
        $runType = PayrollRunType::from($request->run_type);

        $summary = [
            'total_basic'      => 0,
            'total_allowances' => 0,
            'total_deductions' => 0,
            'total_net'        => 0,
            'employee_count'   => $employees->count(),
        ];

        foreach ($employees as $employee) {
            $payslip = $this->payrollService->previewPayslip($employee, $period, $runType);

            $summary['total_basic']      += $payslip['contract_basic'];
            $summary['total_allowances'] += $payslip['totals']['total_allowances'];
            $summary['total_deductions'] += $payslip['totals']['total_deductions'];
            $summary['total_net']        += $payslip['totals']['net_salary'];
        }

        return response()->json([
            'message' => 'تم حساب ملخص الفترة بنجاح',
            'data'    => $summary
        ]);
    }

    /**
     * جلب أرقام الموظفين الذين تم ترحيل رواتبهم
     */
    public function getProcessedEmployees(Request $request): JsonResponse
    {
        $this->authorize('view', PayrollPolicy::class);

        $request->validate([
            'pay_period_id' => 'required|exists:hr_pay_periods,id',
            'run_type'      => 'required|in:regular,overtime_only',
        ]);

        // البحث بناءً على الفترة ونوع المسير المباشرين
        $processedEmployeeIds = Payslip::whereHas('batch', function ($query) use ($request) {
            $query->where('status', 'posted')
                  ->where('pay_period_id', $request->pay_period_id)
                  ->where('run_type', $request->run_type);
        })->distinct()->pluck('employee_id')->toArray();

        return response()->json([
            'data' => $processedEmployeeIds
        ]);
    }

    /**
     * تصدير ملف تحويل الرواتب للبنك
     */
    public function exportBankFile($batchId)
    {
        $this->authorize('view', Employee::class);

        $batch = PayrollBatch::with([
            'payslips.employee.primaryBankAccount'
        ])->findOrFail($batchId);

        $fileName = "Bank_Transfer_Batch_{$batchId}_" . date('Ymd') . ".csv";

        $headers = [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $columns = ['اسم الموظف', 'الرقم الوظيفي', 'اسم البنك', 'رقم الحساب', 'الآيبان (IBAN)', 'المبلغ الصافي'];

        $callback = function() use($batch, $columns) {
            $file = fopen('php://output', 'w');
            fputs($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, $columns);

            foreach ($batch->payslips as $payslip) {
                $employee = $payslip->employee;
                $bankAccount = $employee->primaryBankAccount;

                fputcsv($file, [
                    $employee->full_name,
                    $employee->employee_number,
                    $bankAccount ? $bankAccount->bank_name : 'لم يتم تحديد بنك',
                    $bankAccount ? $bankAccount->account_number : '---',
                    $bankAccount ? $bankAccount->iban : '---',
                    $payslip->net_salary
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
