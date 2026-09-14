<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Purchasing\Http\Requests\Orders\StorePurchaseOrderRequest;
use App\Modules\Purchasing\Http\Requests\Orders\UpdatePurchaseOrderRequest;
use App\Modules\Purchasing\Http\Resources\PurchaseOrderResource;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Services\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class PurchaseOrderController extends Controller
{
    public function __construct(
        protected readonly PurchaseOrderService $orderService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $filters = $request->only([
            'search',
            'status',
            'supplier_id',
            'currency_id',
            'requisition_id',
            'date_from',
            'date_to',
        ]);

        $perPage = (int) $request->input('per_page', 15);
        $orders = $this->orderService->paginate($filters, $perPage);

        return PurchaseOrderResource::collection($orders);
    }

    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        $order = $this->orderService->create(
            $request->validated(),
            (int) $request->user()->id
        );

        return (new PurchaseOrderResource($order))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(PurchaseOrder $order): PurchaseOrderResource
    {
        $this->authorize('view', $order);

        $loadedOrder = $this->orderService->find((int) $order->id);

        return new PurchaseOrderResource($loadedOrder);
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $order): PurchaseOrderResource
    {
        $updatedOrder = $this->orderService->update(
            $order,
            $request->validated(),
            (int) $request->user()->id
        );

        return new PurchaseOrderResource($updatedOrder);
    }

    public function destroy(PurchaseOrder $order): JsonResponse
    {
        $this->authorize('delete', $order);

        $this->orderService->delete($order);

        return response()->json([
            'message' => 'تم حذف أمر الشراء بنجاح.',
        ], Response::HTTP_OK);
    }

    public function confirm(PurchaseOrder $order, Request $request): PurchaseOrderResource
    {
        $this->authorize('confirm', $order);

        $confirmedOrder = $this->orderService->confirm(
            $order,
            (int) $request->user()->id
        );

        return new PurchaseOrderResource($confirmedOrder);
    }

    public function cancel(PurchaseOrder $order, Request $request): PurchaseOrderResource
    {
        $this->authorize('cancel', $order);

        $cancelledOrder = $this->orderService->cancel(
            $order,
            (int) $request->user()->id
        );

        return new PurchaseOrderResource($cancelledOrder);
    }
}