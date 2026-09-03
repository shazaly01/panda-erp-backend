<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Budget;
use App\Modules\Accounting\Services\BudgetService;
use App\Modules\Accounting\Services\Reporting\BudgetVarianceService;
use App\Modules\Accounting\Http\Requests\StoreBudgetRequest;
use App\Modules\Accounting\Http\Requests\UpdateBudgetRequest;
use App\Modules\Accounting\Http\Resources\BudgetResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class BudgetController extends Controller
{
    public function __construct(
        protected BudgetService $budgetService,
        protected BudgetVarianceService $varianceService
    ) {}

    /**
     * عرض قائمة الموازنات التقديرية
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Budget::class);

        $budgets = $this->budgetService->list($request->all());

        return BudgetResource::collection($budgets);
    }

    /**
     * حفظ موازنة تقديرية جديدة
     */
    public function store(StoreBudgetRequest $request): JsonResponse
    {
        $this->authorize('create', Budget::class);

        $budget = $this->budgetService->create($request->validated(), (int) Auth::id());

        return (new BudgetResource($budget))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * عرض تفاصيل موازنة معينة
     */
    public function show(Budget $budget): BudgetResource
    {
        $this->authorize('view', $budget);

        $budget->load(['lines.account', 'lines.costCenter', 'fiscalYear', 'creator', 'approver']);

        return new BudgetResource($budget);
    }

    /**
     * تعديل بيانات وبنود الموازنة
     */
    public function update(UpdateBudgetRequest $request, Budget $budget): BudgetResource
    {
        $this->authorize('update', $budget);

        $updatedBudget = $this->budgetService->update($budget, $request->validated());

        return new BudgetResource($updatedBudget);
    }

    /**
     * حذف موازنة مسودة
     */
    public function destroy(Budget $budget): JsonResponse
    {
        $this->authorize('delete', $budget);

        $this->budgetService->delete($budget);

        return response()->json([
            'message' => 'تم حذف الموازنة التقديرية بنجاح.',
        ]);
    }

    /**
     * اعتماد الموازنة
     */
    public function approve(Budget $budget): BudgetResource
    {
        $this->authorize('approve', $budget);

        $approvedBudget = $this->budgetService->approve($budget, (int) Auth::id());

        return new BudgetResource($approvedBudget);
    }

    /**
     * تفعيل الموازنة المعتمدة
     */
    public function activate(Budget $budget): BudgetResource
    {
        $this->authorize('activate', $budget);

        $activatedBudget = $this->budgetService->activate($budget);

        return new BudgetResource($activatedBudget);
    }

    /**
     * إغلاق الموازنة
     */
    public function close(Budget $budget): BudgetResource
    {
        $this->authorize('close', $budget);

        $closedBudget = $this->budgetService->close($budget);

        return new BudgetResource($closedBudget);
    }

    /**
     * استخراج تقرير مقارنة الفعلي بالمخطط وتحليل الانحرافات
     */
    public function varianceReport(Request $request, Budget $budget): JsonResponse
    {
        $this->authorize('viewVarianceReport', $budget);

        $report = $this->varianceService->getBudgetVarianceReport($budget, $request->all());

        return response()->json([
            'data' => $report,
        ]);
    }
}