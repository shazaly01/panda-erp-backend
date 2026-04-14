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


    public function getBatches(): \Illuminate\Http\JsonResponse
    {
        // التحقق من الصلاحية (يمكنك تغيير المسمى حسب السياسة لديك)
        $this->authorize('view', Employee::class);

        $batches = \App\Modules\HR\Models\PayrollBatch::with(['creator:id,name', 'journalEntry:id,entry_number'])
            ->latest('date')
            ->paginate(15);

        return response()->json($batches);
    }



    /**
     * حساب ملخص المسير (للواجهة الأمامية)
     * يرجع إجمالي الرواتب الأساسية، الاستحقاقات، الاستقطاعات، والصافي
     */
    public function getSummary(\Illuminate\Http\Request $request): JsonResponse
    {
        // التحقق من الصلاحيات
        $this->authorize('view', Employee::class);

        // التحقق من صحة البيانات القادمة
        $request->validate([
            'employee_ids' => 'required|array',
            'employee_ids.*' => 'exists:employees,id',
            'month' => 'required|date_format:Y-m',
        ]);

        $employeeIds = $request->employee_ids;
        $month = $request->month;

        $employees = Employee::whereIn('id', $employeeIds)->get();

        // تهيئة مصفوفة الملخص
        $summary = [
            'total_basic' => 0,
            'total_allowances' => 0,
            'total_deductions' => 0,
            'total_net' => 0,
            'employee_count' => $employees->count(),
        ];

        // المرور على الموظفين وحساب رواتبهم لتجميع الأرقام
        foreach ($employees as $employee) {
            $payslip = $this->payrollService->previewPayslip($employee, $month);

            $summary['total_basic'] += $payslip['contract_basic'];
            $summary['total_allowances'] += $payslip['totals']['total_allowances'];
            $summary['total_deductions'] += $payslip['totals']['total_deductions'];
            $summary['total_net'] += $payslip['totals']['net_salary'];
        }

        return response()->json([
            'message' => 'تم حساب الملخص بنجاح',
            'data' => $summary
        ]);
    }



    /**
     * جلب أرقام الموظفين الذين تم ترحيل رواتبهم لشهر محدد
     * تُستخدم في الواجهة لمنع التحديد المزدوج
     */
    public function getProcessedEmployees(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('view', Employee::class);

        $request->validate([
            'month' => 'required|date_format:Y-m',
        ]);

        // توحيد صيغة التاريخ لبداية الشهر لمطابقته مع ما حفظناه في المسير
        $startOfMonth = \Carbon\Carbon::parse($request->month)->startOfMonth()->format('Y-m-d');

        // جلب IDs الموظفين من جدول القسائم الذين ينتمون لمسير في هذا الشهر وحالته 'مرحل'
        $processedEmployeeIds = \App\Modules\HR\Models\Payslip::whereHas('batch', function ($query) use ($startOfMonth) {
            $query->where('date', $startOfMonth)
                  ->where('status', 'posted');
        })->pluck('employee_id')->toArray();

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
