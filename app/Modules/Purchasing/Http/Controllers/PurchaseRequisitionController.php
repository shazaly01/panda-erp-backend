<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Purchasing\Http\Requests\Requisitions\RejectPurchaseRequisitionRequest;
use App\Modules\Purchasing\Http\Requests\Requisitions\StorePurchaseRequisitionRequest;
use App\Modules\Purchasing\Http\Requests\Requisitions\TriagePurchaseRequisitionRequest;
use App\Modules\Purchasing\Http\Requests\Requisitions\UpdatePurchaseRequisitionRequest;
use App\Modules\Purchasing\Http\Resources\PurchaseIssueResource;
use App\Modules\Purchasing\Http\Resources\PurchaseRequisitionResource;
use App\Modules\Purchasing\Models\PurchaseRequisition;
use App\Modules\Purchasing\Services\PurchaseRequisitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class PurchaseRequisitionController extends Controller
{
    public function __construct(
        protected readonly PurchaseRequisitionService $requisitionService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PurchaseRequisition::class);

        $filters = $request->only([
            'search',
            'status',
            'priority',
            'department_id',
            'requested_by',
            'date_from',
            'date_to',
        ]);

        $perPage = (int) $request->input('per_page', 15);
        $requisitions = $this->requisitionService->paginate($filters, $perPage);

        return PurchaseRequisitionResource::collection($requisitions);
    }

    public function store(StorePurchaseRequisitionRequest $request): JsonResponse
    {
        $requisition = $this->requisitionService->create(
            $request->validated(),
            (int) $request->user()->id
        );

        return (new PurchaseRequisitionResource($requisition))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(PurchaseRequisition $requisition): PurchaseRequisitionResource
    {
        $this->authorize('view', $requisition);

        $loadedRequisition = $this->requisitionService->find((int) $requisition->id);

        return new PurchaseRequisitionResource($loadedRequisition);
    }

    public function update(UpdatePurchaseRequisitionRequest $request, PurchaseRequisition $requisition): PurchaseRequisitionResource
    {
        $updatedRequisition = $this->requisitionService->update(
            $requisition,
            $request->validated(),
            (int) $request->user()->id
        );

        return new PurchaseRequisitionResource($updatedRequisition);
    }

    public function destroy(PurchaseRequisition $requisition): JsonResponse
    {
        $this->authorize('delete', $requisition);

        $this->requisitionService->delete($requisition);

        return response()->json([
            'message' => 'تم حذف طلب الشراء بنجاح.',
        ], Response::HTTP_OK);
    }

    public function submit(PurchaseRequisition $requisition, Request $request): PurchaseRequisitionResource
    {
        $this->authorize('update', $requisition);

        $submittedRequisition = $this->requisitionService->submitForApproval(
            $requisition,
            (int) $request->user()->id
        );

        return new PurchaseRequisitionResource($submittedRequisition);
    }

    public function approve(PurchaseRequisition $requisition, Request $request): PurchaseRequisitionResource
    {
        $this->authorize('approve', $requisition);

        $approvedRequisition = $this->requisitionService->approve(
            $requisition,
            (int) $request->user()->id
        );

        return new PurchaseRequisitionResource($approvedRequisition);
    }

    public function reject(RejectPurchaseRequisitionRequest $request, PurchaseRequisition $requisition): PurchaseRequisitionResource
    {
        $rejectedRequisition = $this->requisitionService->reject(
            $requisition,
            (string) $request->validated('rejection_reason'),
            (int) $request->user()->id
        );

        return new PurchaseRequisitionResource($rejectedRequisition);
    }

    /**
     * قراءة الأرصدة اللحظية للمخزن المختار واقتراح إجراءات الفرز
     */
    public function triageOverview(Request $request, PurchaseRequisition $requisition): JsonResponse
    {
        $this->authorize('view', $requisition);

        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:inventory_warehouses,id'],
        ]);

        $overview = $this->requisitionService->getTriageOverview(
            $requisition,
            (int) $validated['warehouse_id']
        );

        return response()->json($overview, Response::HTTP_OK);
    }

    /**
     * تنفيذ التوزيع الذكي وتوليد مسودة إذن الصرف المخزني فورياً
     */
    public function executeTriage(TriagePurchaseRequisitionRequest $request, PurchaseRequisition $requisition): JsonResponse
    {
        $this->authorize('approve', $requisition);

        $result = $this->requisitionService->executeTriage(
            $requisition,
            $request->validated(),
            (int) $request->user()->id
        );

        return response()->json([
            'message'              => 'تم فرز وتوزيع بنود الطلب وتوليد إذن الصرف بنجاح.',
            'requisition'          => new PurchaseRequisitionResource($result['requisition']),
            'draft_issue'          => $result['draft_issue'] ? new PurchaseIssueResource($result['draft_issue']) : null,
            'issued_items_count'   => $result['issued_items_count'],
            'purchase_items_count' => $result['purchase_items_count'],
        ], Response::HTTP_OK);
    }
}