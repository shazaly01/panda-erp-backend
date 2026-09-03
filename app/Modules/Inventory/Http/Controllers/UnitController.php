<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Http\Requests\Units\StoreUnitRequest;
use App\Modules\Inventory\Http\Requests\Units\UpdateUnitRequest;
use App\Modules\Inventory\Http\Resources\UnitResource;
use App\Modules\Inventory\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UnitController extends Controller
{
    /**
     * عرض قائمة وحدات القياس
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Unit::class);

        $units = Unit::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->search;
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('symbol', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('is_active'), function ($q) use ($request) {
                $q->where('is_active', $request->boolean('is_active'));
            })
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return UnitResource::collection($units);
    }

    /**
     * إنشاء وحدة قياس جديدة
     */
    public function store(StoreUnitRequest $request): JsonResponse
    {
        $this->authorize('create', Unit::class);

        $unit = Unit::create($request->validated());

        return response()->json([
            'message' => 'تم إنشاء وحدة القياس بنجاح.',
            'data' => new UnitResource($unit),
        ], 201);
    }

    /**
     * عرض تفاصيل وحدة قياس معينة
     */
    public function show(Unit $unit): UnitResource
    {
        $this->authorize('view', $unit);

        return new UnitResource($unit);
    }

    /**
     * تعديل وحدة قياس
     */
    public function update(UpdateUnitRequest $request, Unit $unit): JsonResponse
    {
        $this->authorize('update', $unit);

        $unit->update($request->validated());

        return response()->json([
            'message' => 'تم تحديث وحدة القياس بنجاح.',
            'data' => new UnitResource($unit),
        ]);
    }

    /**
     * حذف وحدة قياس
     */
    public function destroy(Unit $unit): JsonResponse
    {
        $this->authorize('delete', $unit);

        $unit->delete();

        return response()->json([
            'message' => 'تم حذف وحدة القياس بنجاح.',
        ]);
    }
}