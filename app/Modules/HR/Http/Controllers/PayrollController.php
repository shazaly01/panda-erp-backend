<?php

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Services\PayrollService;
use App\Modules\HR\Services\PayrollPostingService; // 1. استدعاء خدمة الترحيل الجديدة
use App\Modules\HR\Http\Requests\Payroll\PreviewPayrollRequest;
use App\Modules\HR\Http\Requests\Payroll\PostPayrollBatchRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Exception;

class PayrollController extends Controller
{
    protected PayrollService $payrollService;
    protected PayrollPostingService $payrollPostingService; // 2. تعريف الخاصية

    // 3. حقن الخدمة في البناء
    public function __construct(
        PayrollService $payrollService,
        PayrollPostingService $payrollPostingService
    ) {
        $this->payrollService = $payrollService;
        $this->payrollPostingService = $payrollPostingService;
    }

    /**
     * معاينة قسيمة راتب (قبل الحفظ)
     */
    public function preview(PreviewPayrollRequest $request): JsonResponse
    {
        try {
            $employee = Employee::findOrFail($request->employee_id);
            $externalInputs = $request->input('inputs', []);

            $payslipData = $this->payrollService->previewPayslip($employee, $externalInputs);

            return response()->json([
                'message' => 'تم احتساب معاينة الراتب بنجاح',
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
     * تم تحديث الدالة لتستخدم الـ Request الجديد
     */
    public function postBatch(PostPayrollBatchRequest $request): JsonResponse
    {
        // تم حذف $request->validate([...]) لأن الـ PostPayrollBatchRequest تكفل بالأمر

        try {
            DB::beginTransaction();

            $employees = Employee::whereIn('id', $request->employee_ids)->get();

            $this->payrollPostingService->postPayrollBatch(
                employees: $employees,
                date: $request->date,
                description: $request->description
            );

            DB::commit();

            return response()->json([
                'message' => 'تم اعتماد الرواتب وترحيل القيد المحاسبي بنجاح.',
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'فشلت عملية الترحيل',
                'error' => $e->getMessage()
            ], 422);
        }
    }
}
