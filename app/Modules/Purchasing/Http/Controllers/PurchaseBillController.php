<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Purchasing\Http\Requests\Bills\StorePurchaseBillRequest;
use App\Modules\Purchasing\Http\Requests\Bills\UpdatePurchaseBillRequest;
use App\Modules\Purchasing\Http\Resources\PurchaseBillResource;
use App\Modules\Purchasing\Models\PurchaseBill;
use App\Modules\Purchasing\Services\PurchaseBillService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class PurchaseBillController extends Controller
{
    public function __construct(
        protected readonly PurchaseBillService $billService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PurchaseBill::class);

        $filters = $request->only([
            'search',
            'status',
            'supplier_id',
            'warehouse_id',
            'currency_id',
            'purchase_order_id',
            'receipt_id',
            'date_from',
            'date_to',
        ]);

        $perPage = (int) $request->input('per_page', 15);
        $bills = $this->billService->paginate($filters, $perPage);

        return PurchaseBillResource::collection($bills);
    }

    public function store(StorePurchaseBillRequest $request): JsonResponse
    {
        $data = $request->validated();
        $userId = (int) $request->user()->id;

        $bill = $request->boolean('post_now')
            ? $this->billService->createAndPost($data, $userId)
            : $this->billService->create($data, $userId);

        return (new PurchaseBillResource($bill))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(PurchaseBill $bill): PurchaseBillResource
    {
        $this->authorize('view', $bill);

        $loadedBill = $this->billService->find((int) $bill->id);

        return new PurchaseBillResource($loadedBill);
    }

    public function update(UpdatePurchaseBillRequest $request, PurchaseBill $bill): PurchaseBillResource
    {
        $updatedBill = $this->billService->update(
            $bill,
            $request->validated(),
            (int) $request->user()->id
        );

        return new PurchaseBillResource($updatedBill);
    }

    public function destroy(PurchaseBill $bill): JsonResponse
    {
        $this->authorize('delete', $bill);

        $this->billService->delete($bill);

        return response()->json([
            'message' => 'تم حذف فاتورة الشراء بنجاح.',
        ], Response::HTTP_OK);
    }

    public function post(PurchaseBill $bill, Request $request): PurchaseBillResource
    {
        $this->authorize('post', $bill);

        $postedBill = $this->billService->post(
            $bill,
            (int) $request->user()->id
        );

        return new PurchaseBillResource($postedBill);
    }

    public function cancel(PurchaseBill $bill, Request $request): PurchaseBillResource
    {
        $this->authorize('cancel', $bill);

        $cancelledBill = $this->billService->cancel(
            $bill,
            (int) $request->user()->id
        );

        return new PurchaseBillResource($cancelledBill);
    }
}