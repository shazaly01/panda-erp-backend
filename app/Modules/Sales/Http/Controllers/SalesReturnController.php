<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Http\Requests\Return\StoreSalesReturnRequest;
use App\Modules\Sales\Http\Requests\Return\UpdateSalesReturnRequest;
use App\Modules\Sales\Http\Resources\SalesReturnResource;
use App\Modules\Sales\Models\SalesReturn;
use App\Modules\Sales\Services\SalesReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class SalesReturnController extends Controller
{
    public function __construct(
        protected SalesReturnService $returnService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SalesReturn::class);

        $filters = $request->only([
            'search',
            'status',
            'posting_status',
            'refund_status',
            'partner_id',
            'warehouse_id',
            'branch_id',
            'sales_invoice_id',
            'shift_id',
            'date_from',
            'date_to',
        ]);

        $perPage = (int) $request->input('per_page', 15);
        $returns = $this->returnService->paginate($filters, $perPage);

        return SalesReturnResource::collection($returns);
    }

    public function store(StoreSalesReturnRequest $request): JsonResponse
    {
        $this->authorize('create', SalesReturn::class);

        $salesReturn = $this->returnService->create(
            $request->validated(),
            (int) $request->user()->id
        );

        return (new SalesReturnResource($salesReturn))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(SalesReturn $salesReturn): JsonResponse
    {
        $this->authorize('view', $salesReturn);

        $return = $this->returnService->find((int) $salesReturn->id);

        return (new SalesReturnResource($return))->response();
    }

    public function update(UpdateSalesReturnRequest $request, SalesReturn $salesReturn): JsonResponse
    {
        $this->authorize('update', $salesReturn);

        $return = $this->returnService->update(
            $salesReturn,
            $request->validated(),
            (int) $request->user()->id
        );

        return (new SalesReturnResource($return))->response();
    }

    public function destroy(SalesReturn $salesReturn): JsonResponse
    {
        $this->authorize('delete', $salesReturn);

        $this->returnService->delete($salesReturn);

        return response()->json([
            'message' => 'تم حذف مرتجع المبيعات بنجاح.',
        ], Response::HTTP_OK);
    }

    public function confirm(SalesReturn $salesReturn, Request $request): JsonResponse
    {
        $this->authorize('confirm', $salesReturn);

        $return = $this->returnService->confirm(
            $salesReturn,
            (int) $request->user()->id
        );

        return (new SalesReturnResource($return))->response();
    }

    public function post(SalesReturn $salesReturn, Request $request): JsonResponse
    {
        $this->authorize('post', $salesReturn);

        $return = $this->returnService->post(
            $salesReturn,
            (int) $request->user()->id
        );

        return (new SalesReturnResource($return))->response();
    }

    public function cancel(SalesReturn $salesReturn, Request $request): JsonResponse
    {
        $this->authorize('cancel', $salesReturn);

        $return = $this->returnService->cancel(
            $salesReturn,
            (int) $request->user()->id
        );

        return (new SalesReturnResource($return))->response();
    }
}