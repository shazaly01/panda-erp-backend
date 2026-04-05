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
    }

    /**
     * معاينة قسيمة راتب (قبل الاعتماد والحفظ)
     * التحديث الجديد: تعتمد على "الشهر" لسحب كل المتغيرات آلياً
     */
    public function preview(PreviewPayrollRequest $request): JsonResponse
    {
        try {
            $employee = Employee::findOrFail($request->employee_id);

            // نأخذ الشهر من الطلب (مثال: '2026-04').
            // إذا لم يتم تمريره، نأخذ الشهر الحالي كقيمة افتراضية.
            $month = $request->input('month', now()->format('Y-m'));

            // المحرك الآن يبحث بنفسه عن (السلف، الغياب، الحوافز) الخاصة بهذا الشهر
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
        try {
            DB::beginTransaction();

            $employees = Employee::whereIn('id', $request->employee_ids)->get();

            // خدمة الترحيل ستقوم بإنشاء القيود المحاسبية، وتغيير حالة السلف إلى "مخصومة"،
            // وتغيير حالة المدخلات المتغيرة (is_processed = true)
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
