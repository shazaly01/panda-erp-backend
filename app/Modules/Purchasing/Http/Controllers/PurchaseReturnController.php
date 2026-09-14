<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Purchasing\Http\Requests\Returns\StorePurchaseReturnRequest;
use App\Modules\Purchasing\Http\Requests\Returns\UpdatePurchaseReturnRequest;
use App\Modules\Purchasing\Http\Resources\PurchaseReturnResource;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Modules\Purchasing\Services\PurchaseReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class PurchaseReturnController extends Controller
{
    public function __construct(
        protected readonly PurchaseReturnService $returnService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PurchaseReturn::class);

        $filters = $request->only([
            'search',
            'status',
            'supplier_id',
            'warehouse_id',
            'currency_id',
            'bill_id',
            'date_from',
            'date_to',
        ]);

        $perPage = (int) $request->input('per_page', 15);
        $returns = $this->returnService->paginate($filters, $perPage);

        return PurchaseReturnResource::collection($returns);
    }

    public function store(StorePurchaseReturnRequest $request): JsonResponse
    {
        $return = $this->returnService->create(
            $request->validated(),
            (int) $request->user()->id
        );

        return (new PurchaseReturnResource($return))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(PurchaseReturn $return): PurchaseReturnResource
    {
        $this->authorize('view', $return);

        $loadedReturn = $this->returnService->find((int) $return->id);

        return new PurchaseReturnResource($loadedReturn);
    }

    public function update(UpdatePurchaseReturnRequest $request, PurchaseReturn $return): PurchaseReturnResource
    {
        $updatedReturn = $this->returnService->update(
            $return,
            $request->validated(),
            (int) $request->user()->id
        );

        return new PurchaseReturnResource($updatedReturn);
    }

    public function destroy(PurchaseReturn $return): JsonResponse
    {
        $this->authorize('delete', $return);

        $this->returnService->delete($return);

        return response()->json([
            'message' => 'تم حذف سند مردود المشتريات بنجاح.',
        ], Response::HTTP_OK);
    }

    public function post(PurchaseReturn $return, Request $request): PurchaseReturnResource
    {
        $this->authorize('post', $return);

        $postedReturn = $this->returnService->post(
            $return,
            (int) $request->user()->id
        );

        return new PurchaseReturnResource($postedReturn);
    }

    public function cancel(PurchaseReturn $return, Request $request): PurchaseReturnResource
    {
        $this->authorize('cancel', $return);

        $cancelledReturn = $this->returnService->cancel(
            $return,
            (int) $request->user()->id
        );

        return new PurchaseReturnResource($cancelledReturn);
    }
}