<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Purchasing\Http\Requests\Issues\StorePurchaseIssueRequest;
use App\Modules\Purchasing\Http\Requests\Issues\UpdatePurchaseIssueRequest;
use App\Modules\Purchasing\Http\Resources\PurchaseIssueResource;
use App\Modules\Purchasing\Models\PurchaseIssue;
use App\Modules\Purchasing\Services\PurchaseIssueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class PurchaseIssueController extends Controller
{
    public function __construct(
        protected PurchaseIssueService $issueService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PurchaseIssue::class);

        $filters = $request->only([
            'search',
            'status',
            'warehouse_id',
            'department_id',
            'recipient_id',
            'requisition_id',
            'date_from',
            'date_to',
        ]);

        $perPage = (int) $request->input('per_page', 15);
        $issues = $this->issueService->paginate($filters, $perPage);

        return PurchaseIssueResource::collection($issues);
    }

    public function store(StorePurchaseIssueRequest $request): JsonResponse
    {
        $this->authorize('create', PurchaseIssue::class);

        $issue = $this->issueService->create(
            $request->validated(),
            (int) Auth::id()
        );

        return (new PurchaseIssueResource($issue))
            ->response()
            ->setStatusCode(201);
    }

    public function show(int $id): JsonResponse
    {
        $issue = $this->issueService->find($id);

        $this->authorize('view', $issue);

        return (new PurchaseIssueResource($issue))->response();
    }

    public function update(UpdatePurchaseIssueRequest $request, int $id): JsonResponse
    {
        $issue = $this->issueService->find($id);

        $this->authorize('update', $issue);

        $updatedIssue = $this->issueService->update(
            $issue,
            $request->validated(),
            (int) Auth::id()
        );

        return (new PurchaseIssueResource($updatedIssue))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $issue = $this->issueService->find($id);

        $this->authorize('delete', $issue);

        $this->issueService->delete($issue);

        return response()->json([
            'message' => 'تم حذف مسودة إذن الصرف بنجاح.',
        ]);
    }

    public function confirm(int $id): JsonResponse
    {
        $issue = $this->issueService->find($id);

        $this->authorize('confirm', $issue);

        $confirmedIssue = $this->issueService->confirmIssue(
            $issue,
            (int) Auth::id()
        );

        return (new PurchaseIssueResource($confirmedIssue))->response();
    }

    public function cancel(int $id): JsonResponse
    {
        $issue = $this->issueService->find($id);

        $this->authorize('cancel', $issue);

        $cancelledIssue = $this->issueService->cancelIssue(
            $issue,
            (int) Auth::id()
        );

        return (new PurchaseIssueResource($cancelledIssue))->response();
    }
}