<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Purchasing\Http\Requests\Receipts\StorePurchaseReceiptRequest;
use App\Modules\Purchasing\Http\Requests\Receipts\UpdatePurchaseReceiptRequest;
use App\Modules\Purchasing\Http\Resources\PurchaseReceiptResource;
use App\Modules\Purchasing\Models\PurchaseReceipt;
use App\Modules\Purchasing\Services\PurchaseReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class PurchaseReceiptController extends Controller
{
    public function __construct(
        protected readonly PurchaseReceiptService $receiptService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PurchaseReceipt::class);

        $filters = $request->only([
            'search',
            'status',
            'supplier_id',
            'warehouse_id',
            'purchase_order_id',
            'date_from',
            'date_to',
        ]);

        $perPage = (int) $request->input('per_page', 15);
        $receipts = $this->receiptService->paginate($filters, $perPage);

        return PurchaseReceiptResource::collection($receipts);
    }

    public function store(StorePurchaseReceiptRequest $request): JsonResponse
    {
        $receipt = $this->receiptService->create(
            $request->validated(),
            (int) $request->user()->id
        );

        return (new PurchaseReceiptResource($receipt))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(PurchaseReceipt $receipt): PurchaseReceiptResource
    {
        $this->authorize('view', $receipt);

        $loadedReceipt = $this->receiptService->find((int) $receipt->id);

        return new PurchaseReceiptResource($loadedReceipt);
    }

    public function update(UpdatePurchaseReceiptRequest $request, PurchaseReceipt $receipt): PurchaseReceiptResource
    {
        $updatedReceipt = $this->receiptService->update(
            $receipt,
            $request->validated(),
            (int) $request->user()->id
        );

        return new PurchaseReceiptResource($updatedReceipt);
    }

    public function destroy(PurchaseReceipt $receipt): JsonResponse
    {
        $this->authorize('delete', $receipt);

        $this->receiptService->delete($receipt);

        return response()->json([
            'message' => 'تم حذف سند الاستلام بنجاح.',
        ], Response::HTTP_OK);
    }

    public function receive(PurchaseReceipt $receipt, Request $request): PurchaseReceiptResource
    {
        $this->authorize('receive', $receipt);

        $receivedReceipt = $this->receiptService->receive(
            $receipt,
            (int) $request->user()->id
        );

        return new PurchaseReceiptResource($receivedReceipt);
    }

    public function cancel(PurchaseReceipt $receipt, Request $request): PurchaseReceiptResource
    {
        $this->authorize('cancel', $receipt);

        $cancelledReceipt = $this->receiptService->cancel(
            $receipt,
            (int) $request->user()->id
        );

        return new PurchaseReceiptResource($cancelledReceipt);
    }
}