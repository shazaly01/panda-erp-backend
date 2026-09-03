<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Http\Requests\Adjustments\StoreAdjustmentRequest;
use App\Modules\Inventory\Http\Requests\Adjustments\UpdateAdjustmentRequest;
use App\Modules\Inventory\Http\Resources\AdjustmentResource;
use App\Modules\Inventory\Models\Adjustment;
use App\Modules\Inventory\Services\AdjustmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdjustmentController extends Controller
{
    public function __construct(
        protected AdjustmentService $adjustmentService
    ) {}

    /**
     * عرض قائمة طلبات التسوية / الأرصدة الافتتاحية مع الفلترة والتصفح
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Adjustment::class);

        $adjustments = Adjustment::query()
            ->with(['warehouse', 'creator', 'approver'])
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->integer('warehouse_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')->toString()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search')->toString();
                $q->where('adjustment_number', 'like', "%{$search}%");
            })
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return AdjustmentResource::collection($adjustments);
    }

    /**
     * إنشاء طلب تسوية / رصيد افتتاحي جديد (مع دعم خيار الحفظ والاعتماد الفوري)
     */
    public function store(StoreAdjustmentRequest $request): JsonResponse
    {
        $this->authorize('create', Adjustment::class);

        $adjustment = $this->adjustmentService->createAdjustment(
            $request->validated(),
            $request->user()->id
        );

        // خيار الاعتماد الفوري في نفس الطلب لتسريع العمليات الميدانية
        if ($request->boolean('auto_approve') || $request->input('status') === 'approved') {
            $this->authorize('approve', $adjustment);
            $adjustment = $this->adjustmentService->approveAdjustment(
                $adjustment,
                $request->user()->id
            );
        }

        return response()->json([
            'message' => $adjustment->status === 'approved' 
                ? 'تم إنشاء واعتماد مستند التسوية وتطبيق الأثر المخزني والمالي بنجاح.' 
                : 'تم إنشاء مستند التسوية بنجاح.',
            'data'    => new AdjustmentResource($adjustment),
        ], 201);
    }

    /**
     * عرض تفاصيل مستند تسوية معين
     */
    public function show(Adjustment $adjustment): AdjustmentResource
    {
        $this->authorize('view', $adjustment);

        $adjustment->load([
            'warehouse',
            'creator',
            'approver',
            'items.product',
            'items.productUnit.unit',
            'items.batch',
            'journalEntry.details.account',
        ]);

        return new AdjustmentResource($adjustment);
    }

    /**
     * تحديث طلب التسوية (قبل الاعتماد)
     */
    public function update(UpdateAdjustmentRequest $request, Adjustment $adjustment): JsonResponse
    {
        $this->authorize('update', $adjustment);

        $updatedAdjustment = $this->adjustmentService->updateAdjustment(
            $adjustment,
            $request->validated()
        );

        // إمكانية الاعتماد مباشرة عند التحديث
        if ($request->boolean('auto_approve') || $request->input('status') === 'approved') {
            $this->authorize('approve', $updatedAdjustment);
            $updatedAdjustment = $this->adjustmentService->approveAdjustment(
                $updatedAdjustment,
                $request->user()->id
            );
        }

        return response()->json([
            'message' => 'تم تحديث مستند التسوية بنجاح.',
            'data'    => new AdjustmentResource($updatedAdjustment),
        ]);
    }

    /**
     * اعتماد طلب التسوية وتطبيق أثرها المخزني والمالي المباشر
     */
    public function approve(Request $request, Adjustment $adjustment): JsonResponse
    {
        $this->authorize('approve', $adjustment);

        $approvedAdjustment = $this->adjustmentService->approveAdjustment(
            $adjustment,
            $request->user()->id
        );

        return response()->json([
            'message' => 'تم اعتماد طلب التسوية وتطبيق الأثر المخزني والمالي بنجاح.',
            'data'    => new AdjustmentResource($approvedAdjustment),
        ]);
    }

    /**
     * حذف طلب التسوية وعكس أي حركات مرتبطة به (للمسودات فقط)
     */
    public function destroy(Adjustment $adjustment): JsonResponse
    {
        $this->authorize('delete', $adjustment);

        $this->adjustmentService->deleteAdjustment($adjustment);

        return response()->json([
            'message' => 'تم حذف مستند التسوية بنجاح.',
        ]);
    }
}