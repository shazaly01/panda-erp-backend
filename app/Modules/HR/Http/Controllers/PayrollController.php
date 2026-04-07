<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Services\PayrollService;
use App\Modules\HR\Services\PayrollPostingService;
use App\Modules\HR\Http\Requests\Payroll\PreviewPayrollRequest;
use App\Modules\HR\Http\Requests\Payroll\PostPayrollBatchRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Exception;

class PayrollController extends Controller
{
    public function __construct(
        private readonly PayrollService $payrollService,
        private readonly PayrollPostingService $payrollPostingService
    ) {
        // حماية المتحكم بالكامل بالتأكد من تسجيل الدخول
        $this->middleware('auth:sanctum');
    }

    /**
     * معاينة قسيمة راتب (قبل الاعتماد والحفظ)
     */
    public function preview(PreviewPayrollRequest $request): JsonResponse
    {
        // التحقق من الصلاحية عبر السياسة (PayrollPolicy)
        // نستخدم المسمى 'view' لأنها عملية عرض بيانات مالية
        $this->authorize('view', Employee::class);

        try {
            $employee = Employee::findOrFail($request->employee_id);
            $month = $request->input('month', now()->format('Y-m'));

            $payslipData = $this->payrollService->previewPayslip($employee, $month);

            return response()->json([
                'message' => "تم احتساب معاينة الراتب لشهر {$month} بنجاح",
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
    public function postBatch(PostPayrollBatchRequest $request): JsonResponse
    {
        // التحقق من الصلاحية عبر السياسة (PayrollPolicy)
        // نستخدم 'post' أو 'manage' حسب ما هو معرف في ملف السياسة لديك
        $this->authorize('post', Employee::class);

        try {
            DB::beginTransaction();

            $employees = Employee::whereIn('id', $request->employee_ids)->get();

            // تنفيذ عملية الترحيل المحاسبي وتصفية الأرصدة (العهد والسلف)
            $this->payrollPostingService->postPayrollBatch(
                employees: $employees,
                date: $request->date,
                description: $request->description
            );

            DB::commit();

            return response()->json([
                'message' => 'تم اعتماد الرواتب وترحيل القيد المحاسبي وتصفية الأرصدة بنجاح.',
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
