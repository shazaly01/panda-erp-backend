<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Http\Requests\WarehouseLocations\StoreWarehouseLocationRequest;
use App\Modules\Inventory\Http\Requests\WarehouseLocations\UpdateWarehouseLocationRequest;
use App\Modules\Inventory\Http\Resources\WarehouseLocationResource;
use App\Modules\Inventory\Models\WarehouseLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WarehouseLocationController extends Controller
{
    /**
     * عرض قائمة مواقع المستودعات
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', WarehouseLocation::class);

        $locations = WarehouseLocation::query()
            ->with(['warehouse', 'parent', 'children'])
            ->when($request->filled('warehouse_id'), function ($q) use ($request) {
                $q->where('warehouse_id', $request->integer('warehouse_id'));
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->search;
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('type', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('is_active'), function ($q) use ($request) {
                $q->where('is_active', $request->boolean('is_active'));
            })
            ->when($request->has('is_parent'), function ($q) use ($request) {
                if ($request->boolean('is_parent')) {
                    $q->whereNull('parent_id');
                }
            })
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return WarehouseLocationResource::collection($locations);
    }

    /**
     * إنشاء موقع مستودع جديد
     */
    public function store(StoreWarehouseLocationRequest $request): JsonResponse
    {
        $this->authorize('create', WarehouseLocation::class);

        $location = WarehouseLocation::create($request->validated());

        return response()->json([
            'message' => 'تم إنشاء موقع المستودع بنجاح.',
            'data' => new WarehouseLocationResource($location->load(['warehouse', 'parent', 'children'])),
        ], 201);
    }

    /**
     * عرض تفاصيل موقع معين
     */
    public function show(WarehouseLocation $warehouseLocation): WarehouseLocationResource
    {
        $this->authorize('view', $warehouseLocation);

        return new WarehouseLocationResource($warehouseLocation->load(['warehouse', 'parent', 'children']));
    }

    /**
     * تعديل بيانات موقع مستودع
     */
    public function update(UpdateWarehouseLocationRequest $request, WarehouseLocation $warehouseLocation): JsonResponse
    {
        $this->authorize('update', $warehouseLocation);

        $warehouseLocation->update($request->validated());

        return response()->json([
            'message' => 'تم تحديث موقع المستودع بنجاح.',
            'data' => new WarehouseLocationResource($warehouseLocation->load(['warehouse', 'parent', 'children'])),
        ]);
    }

    /**
     * حذف موقع مستودع
     */
    public function destroy(WarehouseLocation $warehouseLocation): JsonResponse
    {
        $this->authorize('delete', $warehouseLocation);

        $warehouseLocation->delete();

        return response()->json([
            'message' => 'تم حذف موقع المستودع بنجاح.',
        ]);
    }
}