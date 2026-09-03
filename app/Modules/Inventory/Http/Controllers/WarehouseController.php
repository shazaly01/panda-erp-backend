<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Services\AccountMappingService;
use App\Modules\Inventory\Http\Requests\Warehouses\StoreWarehouseRequest;
use App\Modules\Inventory\Http\Requests\Warehouses\UpdateWarehouseRequest;
use App\Modules\Inventory\Http\Resources\WarehouseResource;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WarehouseController extends Controller
{
    public function __construct(
        private readonly AccountMappingService $accountMappingService
    ) {}

    /**
     * عرض قائمة المستودعات
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Warehouse::class);

        $warehouses = Warehouse::query()
            ->with(['manager', 'account'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->search;
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('is_active'), function ($q) use ($request) {
                $q->where('is_active', $request->boolean('is_active'));
            })
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return WarehouseResource::collection($warehouses);
    }

    /**
     * إنشاء مستودع جديد
     */
    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        $this->authorize('create', Warehouse::class);

        $data = $request->validated();

        if (empty($data['account_id'])) {
            $data['account_id'] = $this->accountMappingService->getAccountId('inventory_asset');
        }

        $warehouse = Warehouse::create($data);

        return response()->json([
            'message' => 'تم إنشاء المستودع بنجاح.',
            'data' => new WarehouseResource($warehouse->load(['manager', 'account'])),
        ], 201);
    }

    /**
     * عرض تفاصيل مستودع معين
     */
    public function show(Warehouse $warehouse): WarehouseResource
    {
        $this->authorize('view', $warehouse);

        return new WarehouseResource($warehouse->load(['manager', 'account']));
    }

    /**
     * تعديل بيانات مستودع
     */
    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): JsonResponse
    {
        $this->authorize('update', $warehouse);

        $data = $request->validated();

        if (array_key_exists('account_id', $data) && empty($data['account_id'])) {
            $data['account_id'] = $this->accountMappingService->getAccountId('inventory_asset');
        }

        $warehouse->update($data);

        return response()->json([
            'message' => 'تم تحديث بيانات المستودع بنجاح.',
            'data' => new WarehouseResource($warehouse->load(['manager', 'account'])),
        ]);
    }

    /**
     * حذف مستودع
     */
    public function destroy(Warehouse $warehouse): JsonResponse
    {
        $this->authorize('delete', $warehouse);

        $warehouse->delete();

        return response()->json([
            'message' => 'تم حذف المستودع بنجاح.',
        ]);
    }
}