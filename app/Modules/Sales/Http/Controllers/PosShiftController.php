<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Http\Requests\Shift\ClosePosShiftRequest;
use App\Modules\Sales\Http\Requests\Shift\StorePosShiftRequest;
use App\Modules\Sales\Http\Resources\PosShiftResource;
use App\Modules\Sales\Models\PosShift;
use App\Modules\Sales\Services\PosShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class PosShiftController extends Controller
{
    public function __construct(
        protected PosShiftService $posShiftService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PosShift::class);

        $filters = $request->only([
            'search',
            'status',
            'user_id',
            'warehouse_id',
            'branch_id',
            'date_from',
            'date_to',
        ]);

        $perPage = (int) $request->input('per_page', 15);
        $shifts = $this->posShiftService->paginate($filters, $perPage);

        return PosShiftResource::collection($shifts);
    }

    public function current(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PosShift::class);

        $branchId = $request->has('branch_id') ? (int) $request->input('branch_id') : null;
        $currentShift = $this->posShiftService->getCurrentOpenShift(
            (int) $request->user()->id,
            $branchId
        );

        if (! $currentShift) {
            return response()->json([
                'message' => 'لا توجد وردية مفتوحة حالياً لهذا المستخدم.',
                'data' => null,
            ], Response::HTTP_OK);
        }

        return (new PosShiftResource($currentShift))->response();
    }

    public function store(StorePosShiftRequest $request): JsonResponse
    {
        $this->authorize('create', PosShift::class);

        $shift = $this->posShiftService->open(
            $request->validated(),
            (int) $request->user()->id
        );

        return (new PosShiftResource($shift))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(PosShift $posShift): JsonResponse
    {
        $this->authorize('view', $posShift);

        $shift = $this->posShiftService->find((int) $posShift->id);

        return (new PosShiftResource($shift))->response();
    }

    public function close(ClosePosShiftRequest $request, PosShift $posShift): JsonResponse
    {
        $this->authorize('close', $posShift);

        $shift = $this->posShiftService->close(
            $posShift,
            $request->validated(),
            (int) $request->user()->id
        );

        return (new PosShiftResource($shift))->response();
    }

    public function reconcile(PosShift $posShift): JsonResponse
    {
        $this->authorize('reconcile', $posShift);

        $totals = $this->posShiftService->calculateShiftTotals($posShift);

        return response()->json([
            'message' => 'تم استخراج ملخص ومطابقة مبالغ الوردية بنجاح.',
            'data' => $totals,
        ], Response::HTTP_OK);
    }
}