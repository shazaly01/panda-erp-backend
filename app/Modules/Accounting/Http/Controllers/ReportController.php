<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Modules\Accounting\Services\Reporting\AccountStatementService;
use App\Modules\Accounting\Models\Box;
use App\Modules\Accounting\Models\BankAccount;
use Illuminate\Validation\ValidationException;
use App\Modules\Accounting\Services\Reporting\TrialBalanceService;
use App\Modules\Accounting\Services\Reporting\IncomeStatementService;
use App\Modules\Accounting\Services\Reporting\BalanceSheetService;

class ReportController extends Controller
{
    public function __construct(
        protected AccountStatementService $statementService,
        protected TrialBalanceService $trialBalanceService,
        protected IncomeStatementService $incomeStatementService,
        protected BalanceSheetService $balanceSheetService,
    ) {}

    /**
     * واجهة برمجة استخراج كشف الحساب
     */
    public function getAccountStatement(Request $request): JsonResponse
    {
        // 1. التحقق من المدخلات القادمة من الواجهة
        $validated = $request->validate([
            'from_date'      => ['required', 'date'],
            'to_date'        => ['required', 'date', 'after_or_equal:from_date'],
            'target_type'    => ['required', 'string', 'in:account,box,bank,customer,supplier'],
            'target_id'      => ['required', 'integer'],
            'cost_center_id' => ['nullable', 'integer', 'exists:cost_centers,id'],
            'include_drafts' => ['nullable', 'boolean'], // [جديد] استقبال حقل تضمين المسودات
        ]);

        // 2. طبقة الترجمة (Translation Layer)
        $resolvedTarget = $this->resolveTarget(
            $validated['target_type'],
            (int) $validated['target_id']
        );

        // 3. استدعاء محرك التقرير (الـ Service)
       $reportData = $this->statementService->getStatement(
            accountId: $resolvedTarget['account_id'],
            fromDate: $validated['from_date'],
            toDate: $validated['to_date'],
            costCenterId: $validated['cost_center_id'] ?? null,
            partyType: $resolvedTarget['party_type'],
            partyId: $resolvedTarget['party_id'],
            includeDrafts: $request->boolean('include_drafts') // الحل هنا: تحويل آمن وحقيقي لـ bool
        );

        // 4. إرجاع النتيجة
        return response()->json([
            'success' => true,
            'data'    => $reportData
        ]);
    }

    /**
     * المترجم الذكي: يحول الكيان إلى بيانات محاسبية
     */
    protected function resolveTarget(string $type, int $id): array
    {
        $accountId = null;
        $partyType = null;
        $partyId = null;

        switch ($type) {
            case 'account':
                // حساب مالي مباشر من شجرة الحسابات
                $accountId = $id;
                break;

            case 'box':
                // خزينة: نجلب رقم الحساب المرتبط بها ونمرر الـ Party
                $box = Box::findOrFail($id);
                $accountId = $box->account_id;
                $partyType = Box::class;
                $partyId = $box->id;
                break;

            case 'bank':
                // بنك: نجلب رقم الحساب المرتبط به ونمرر الـ Party
                $bank = BankAccount::findOrFail($id);
                $accountId = $bank->account_id;
                $partyType = BankAccount::class;
                $partyId = $bank->id;
                break;

            case 'customer':
                // مثال: إذا كان لديك موديل Customer
                // $customer = Customer::findOrFail($id);
                // $accountId = 1200; // يجب جلب حساب العملاء التجميعي من الإعدادات
                // $partyType = Customer::class;
                // $partyId = $customer->id;
                // break;

            default:
                throw ValidationException::withMessages([
                    'target_type' => ['نوع الكيان غير مدعوم في كشف الحساب.']
                ]);
        }

        if (!$accountId) {
            throw ValidationException::withMessages([
                'target' => ['الكيان المحدد غير مربوط بحساب مالي في الدليل.']
            ]);
        }

        return [
            'account_id' => $accountId,
            'party_type' => $partyType,
            'party_id'   => $partyId,
        ];
    }



    /**
     * واجهة برمجة استخراج ميزان المراجعة
     */
    public function getTrialBalance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'as_of_date'     => ['required', 'date'],
            'include_drafts' => ['nullable', 'boolean'],
        ]);

        $reportData = $this->trialBalanceService->getTrialBalance(
            $validated['as_of_date'],
            $request->boolean('include_drafts')
        );

        return response()->json([
            'success' => true,
            'data'    => $reportData
        ]);
    }


    /**
     * واجهة برمجة استخراج قائمة الدخل (الأرباح والخسائر)
     */
    public function getIncomeStatement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_date'      => ['required', 'date'],
            'to_date'        => ['required', 'date', 'after_or_equal:from_date'],
            'include_drafts' => ['nullable', 'boolean'],
        ]);

        $reportData = $this->incomeStatementService->getIncomeStatement(
            $validated['from_date'],
            $validated['to_date'],
            $request->boolean('include_drafts')
        );

        return response()->json([
            'success' => true,
            'data'    => $reportData
        ]);
    }


    /**
     * واجهة برمجة استخراج الميزانية العمومية
     */
    public function getBalanceSheet(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'as_of_date'     => ['required', 'date'],
            'include_drafts' => ['nullable', 'boolean'],
        ]);

        $reportData = $this->balanceSheetService->getBalanceSheet(
            $validated['as_of_date'],
            $request->boolean('include_drafts')
        );

        return response()->json([
            'success' => true,
            'data'    => $reportData
        ]);
    }
}
