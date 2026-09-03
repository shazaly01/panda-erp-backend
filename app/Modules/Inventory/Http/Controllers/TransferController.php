<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Http\Requests\Transfers\StoreTransferRequest;
use App\Modules\Inventory\Http\Requests\Transfers\UpdateTransferRequest;
use App\Modules\Inventory\Http\Resources\TransferResource;
use App\Modules\Inventory\Models\Transfer;
use App\Modules\Inventory\Services\TransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TransferController extends Controller
{
    public function __construct(
        protected TransferService $transferService
    ) {}

    /**
     * عرض قائمة بأوامر التحويل المخزني
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Transfer::class);

        $query = Transfer::query()
            ->with(['fromWarehouse', 'toWarehouse', 'creator', 'approver'])
            ->latest('transfer_date');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('from_warehouse_id')) {
            $query->where('from_warehouse_id', $request->input('from_warehouse_id'));
        }

        if ($request->filled('to_warehouse_id')) {
            $query->where('to_warehouse_id', $request->input('to_warehouse_id'));
        }

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('transfer_number', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->input('per_page', 15);
        $transfers = $query->paginate($perPage);

        return TransferResource::collection($transfers);
    }

    /**
     * إنشاء أمر تحويل مخزني جديد
     */
    public function store(StoreTransferRequest $request): JsonResponse
    {
        $transfer = $this->transferService->createTransfer(
            $request->validated(),
            (int) $request->user()->id
        );

        return (new TransferResource($transfer))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * عرض تفاصيل أمر تحويل محدد
     */
    public function show(Transfer $transfer): TransferResource
    {
        $this->authorize('view', $transfer);

        $transfer->load([
            'fromWarehouse',
            'toWarehouse',
            'creator',
            'approver',
            'items.product',
            'items.productUnit.unit',
            'items.fromLocation',
            'items.toLocation',
            'items.batch',
        ]);

        return new TransferResource($transfer);
    }

    /**
     * تحديث بيانات أمر تحويل (للمسودات والطلبات غير المكتملة)
     */
    public function update(UpdateTransferRequest $request, Transfer $transfer): TransferResource
    {
        $updatedTransfer = $this->transferService->updateTransfer(
            $transfer,
            $request->validated()
        );

        return new TransferResource($updatedTransfer);
    }

    /**
     * حذف أمر التحويل
     */
    public function destroy(Transfer $transfer): JsonResponse
    {
        $this->authorize('delete', $transfer);

        $this->transferService->deleteTransfer($transfer);

        return response()->json([
            'message' => 'تم حذف أمر التحويل بنجاح.',
        ]);
    }

    /**
     * اعتماد وإكمال أمر التحويل وتطبيق الأثر المخزني والمالي
     */
    public function complete(Request $request, Transfer $transfer): TransferResource
    {
        $this->authorize('approve', $transfer);

        $completedTransfer = $this->transferService->completeTransfer(
            $transfer,
            (int) $request->user()->id
        );

        return new TransferResource($completedTransfer);
    }

    /**
     * إلغاء أمر التحويل
     */
    public function cancel(Transfer $transfer): TransferResource
    {
        $this->authorize('update', $transfer);

        $cancelledTransfer = $this->transferService->cancelTransfer($transfer);

        return new TransferResource($cancelledTransfer);
    }
}