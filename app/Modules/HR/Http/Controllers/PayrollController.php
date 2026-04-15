<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Services\PayrollService;
use App\Modules\HR\Services\PayrollPostingService;
use App\Modules\HR\Http\Requests\Payroll\PreviewPayrollRequest;
use App\Modules\HR\Http\Requests\Payroll\PostPayrollBatchRequest;
use App\Modules\HR\Policies\PayrollPolicy;
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
        $this->authorize('preview', PayrollPolicy::class);

        try {
            $employee = Employee::findOrFail($request->employee_id);

            // تمرير التواريخ الجديدة للخدمة
            $payslipData = $this->payrollService->previewPayslip(
                $employee,
                $request->start_date,
                $request->end_date
            );

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
    public function postBatch(PostPayrollBatchRequest $request): JsonResponse
    {
       $this->authorize('postBatch', PayrollPolicy::class);

        try {
            DB::beginTransaction();

            $employees = Employee::whereIn('id', $request->employee_ids)->get();

            // تنفيذ الترحيل باستخدام التواريخ المرنة (أسبوعي/شهري)
            $this->payrollPostingService->postPayrollBatch(
                employees: $employees,
                startDate: $request->start_date, // التعديل هنا
                endDate:   $request->end_date,   // التعديل هنا
                description: $request->description
            );

            DB::commit();
            return response()->json(['message' => 'تم اعتماد الرواتب بنجاح.']);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'فشلت عملية الترحيل', 'error' => $e->getMessage()], 422);
        }
    }


  public function getBatches(): \Illuminate\Http\JsonResponse
    {
        $this->authorize('view', \App\Modules\HR\Policies\PayrollPolicy::class);

        $batches = \App\Modules\HR\Models\PayrollBatch::with(['creator:id,name', 'journalEntry:id,entry_number'])
            ->latest('start_date') // <--- تم التعديل هنا لتجنب خطأ Unknown column 'date'
            ->paginate(15);

        return response()->json($batches);
    }



 /**
     * حساب ملخص المسير (للواجهة الأمامية)
     * يدعم الآن الفترات المرنة (أسبوعية/شهرية)
     */
    public function getSummary(\Illuminate\Http\Request $request): JsonResponse
    {
        // 1. التحقق من الصلاحيات
        $this->authorize('view', PayrollPolicy::class);

        // 2. التحقق من صحة البيانات (استبدال month بـ start_date و end_date)
        $request->validate([
            'employee_ids'   => 'required|array',
            'employee_ids.*' => 'exists:employees,id',
            'start_date'     => 'required|date',
            'end_date'       => 'required|date|after_or_equal:start_date',
        ]);

        $employeeIds = $request->employee_ids;
        $startDate   = $request->start_date;
        $endDate     = $request->end_date;

        $employees = Employee::whereIn('id', $employeeIds)->get();

        $summary = [
            'total_basic'      => 0,
            'total_allowances' => 0,
            'total_deductions' => 0,
            'total_net'        => 0,
            'employee_count'   => $employees->count(),
        ];

        // 3. المرور على الموظفين وحساب رواتبهم بناءً على الفترة المحددة
        foreach ($employees as $employee) {
            // تمرير التواريخ الجديدة للمحرك
            $payslip = $this->payrollService->previewPayslip($employee, $startDate, $endDate);

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
     * جلب أرقام الموظفين الذين تم ترحيل رواتبهم لفترة محددة
     * معدلة لمنع الازدواجية في الفترات المرنة (أسبوعي/شهري)
     */
    public function getProcessedEmployees(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('view', \App\Modules\HR\Policies\PayrollPolicy::class);

        // 1. التحقق من المدخلات الجديدة (الفترة المطلوبة)
        $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = $request->start_date;
        $endDate   = $request->end_date;

        /**
         * 2. البحث عن أي قسيمة راتب (Payslip) تقع ضمن مسير (Batch)
         * يتقاطع تاريخه مع الفترة المختارة حالياً.
         * منطق التقاطع: (تاريخ بداية المسير <= نهاية الفترة المطلوبة)
         * AND (تاريخ نهاية المسير >= بداية الفترة المطلوبة)
         */
        $processedEmployeeIds = \App\Modules\HR\Models\Payslip::whereHas('batch', function ($query) use ($startDate, $endDate) {
            $query->where('status', 'posted')
                  ->where(function ($q) use ($startDate, $endDate) {
                      $q->where('start_date', '<=', $endDate)
                        ->where('end_date', '>=', $startDate);
                  });
        })->distinct()->pluck('employee_id')->toArray();

        return response()->json([
            'data' => $processedEmployeeIds
        ]);
    }


    /**
     * تصدير ملف تحويل الرواتب للبنك (WPS / Bank Export)
     * بناءً على رقم المسير (Batch ID)
     */
    public function exportBankFile($batchId)
    {
        // التحقق من الصلاحيات
        $this->authorize('view', \App\Modules\HR\Models\Employee::class);

        // جلب المسير مع قسائم الراتب، والموظفين، وحساباتهم البنكية الأساسية
        $batch = \App\Modules\HR\Models\PayrollBatch::with([
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

        // الأعمدة التي يتطلبها البنك عادةً
        $columns = ['اسم الموظف', 'الرقم الوظيفي', 'اسم البنك', 'رقم الحساب', 'الآيبان (IBAN)', 'المبلغ الصافي'];

        $callback = function() use($batch, $columns) {
            $file = fopen('php://output', 'w');

            // إضافة BOM لدعم اللغة العربية بشكل صحيح عند فتح الملف في برنامج Excel
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

        // إرجاع الملف كـ Stream ليتم تحميله مباشرة في المتصفح
        return response()->stream($callback, 200, $headers);
    }
}
