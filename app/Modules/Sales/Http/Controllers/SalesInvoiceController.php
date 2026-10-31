<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Http\Requests\Invoice\StoreSalesInvoiceRequest;
use App\Modules\Sales\Http\Requests\Invoice\UpdateSalesInvoiceRequest;
use App\Modules\Sales\Http\Resources\SalesInvoiceResource;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Services\SalesInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class SalesInvoiceController extends Controller
{
    public function __construct(
        protected SalesInvoiceService $invoiceService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SalesInvoice::class);

        $filters = $request->only([
            'search',
            'status',
            'payment_status',
            'posting_status',
            'partner_id',
            'warehouse_id',
            'branch_id',
            'shift_id',
            'channel',
            'order_type',
            'date_from',
            'date_to',
        ]);

        $perPage = (int) $request->input('per_page', 15);
        $invoices = $this->invoiceService->paginate($filters, $perPage);

        return SalesInvoiceResource::collection($invoices);
    }

    public function store(StoreSalesInvoiceRequest $request): JsonResponse
    {
        $this->authorize('create', SalesInvoice::class);

        $invoice = $this->invoiceService->create(
            $request->validated(),
            (int) $request->user()->id
        );

        return (new SalesInvoiceResource($invoice))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(SalesInvoice $salesInvoice): JsonResponse
    {
        $this->authorize('view', $salesInvoice);

        $invoice = $this->invoiceService->find((int) $salesInvoice->id);

        return (new SalesInvoiceResource($invoice))->response();
    }

    public function update(UpdateSalesInvoiceRequest $request, SalesInvoice $salesInvoice): JsonResponse
    {
        $this->authorize('update', $salesInvoice);

        $invoice = $this->invoiceService->update(
            $salesInvoice,
            $request->validated(),
            (int) $request->user()->id
        );

        return (new SalesInvoiceResource($invoice))->response();
    }

    public function destroy(SalesInvoice $salesInvoice): JsonResponse
    {
        $this->authorize('delete', $salesInvoice);

        $this->invoiceService->delete($salesInvoice);

        return response()->json([
            'message' => 'تم حذف فاتورة المبيعات بنجاح.',
        ], Response::HTTP_OK);
    }

    public function confirm(SalesInvoice $salesInvoice, Request $request): JsonResponse
    {
        $this->authorize('confirm', $salesInvoice);

        $invoice = $this->invoiceService->confirm(
            $salesInvoice,
            (int) $request->user()->id
        );

        return (new SalesInvoiceResource($invoice))->response();
    }

    public function post(SalesInvoice $salesInvoice, Request $request): JsonResponse
    {
        $this->authorize('post', $salesInvoice);

        $invoice = $this->invoiceService->post(
            $salesInvoice,
            (int) $request->user()->id
        );

        return (new SalesInvoiceResource($invoice))->response();
    }

    public function cancel(SalesInvoice $salesInvoice, Request $request): JsonResponse
    {
        $this->authorize('cancel', $salesInvoice);

        $invoice = $this->invoiceService->cancel(
            $salesInvoice,
            (int) $request->user()->id
        );

        return (new SalesInvoiceResource($invoice))->response();
    }
}