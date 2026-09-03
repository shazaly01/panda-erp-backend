<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Http\Requests\PriceLists\StorePriceListRequest;
use App\Modules\Inventory\Http\Requests\PriceLists\UpdatePriceListRequest;
use App\Modules\Inventory\Http\Resources\PriceListResource;
use App\Modules\Inventory\Models\PriceList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class PriceListController extends Controller
{
    /**
     * عرض قائمة قوائم الأسعار
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PriceList::class);

        $priceLists = PriceList::query()
            ->with(['currency', 'prices.product', 'prices.productUnit'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->search;
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('is_active'), function ($q) use ($request) {
                $q->where('is_active', $request->boolean('is_active'));
            })
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return PriceListResource::collection($priceLists);
    }

    /**
     * إنشاء قائمة أسعار جديدة مع أسعار العناصر
     */
    public function store(StorePriceListRequest $request): JsonResponse
    {
        $this->authorize('create', PriceList::class);

        $validated = $request->validated();

        $priceList = DB::transaction(function () use ($validated) {
            if (!empty($validated['is_default']) && $validated['is_default'] === true) {
                PriceList::query()->update(['is_default' => false]);
            }

            $priceList = PriceList::create([
                'currency_id' => $validated['currency_id'],
                'name' => $validated['name'],
                'code' => $validated['code'] ?? null,
                'is_default' => $validated['is_default'] ?? false,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            if (!empty($validated['items'])) {
                $priceList->prices()->createMany($validated['items']);
            }

            return $priceList;
        });

        return response()->json([
            'message' => 'تم إنشاء قائمة الأسعار بنجاح.',
            'data' => new PriceListResource($priceList->load(['currency', 'prices.product', 'prices.productUnit'])),
        ], 201);
    }

    /**
     * عرض تفاصيل قائمة أسعار معينة
     */
    public function show(PriceList $priceList): PriceListResource
    {
        $this->authorize('view', $priceList);

        return new PriceListResource($priceList->load(['currency', 'prices.product', 'prices.productUnit']));
    }

    /**
     * تحديث قائمة أسعار وبنودها
     */
    public function update(UpdatePriceListRequest $request, PriceList $priceList): JsonResponse
    {
        $this->authorize('update', $priceList);

        $validated = $request->validated();

        DB::transaction(function () use ($priceList, $validated) {
            if (!empty($validated['is_default']) && $validated['is_default'] === true) {
                PriceList::query()
                    ->where('id', '!=', $priceList->id)
                    ->update(['is_default' => false]);
            }

            $priceList->update([
                'currency_id' => $validated['currency_id'],
                'name' => $validated['name'],
                'code' => $validated['code'] ?? null,
                'is_default' => $validated['is_default'] ?? false,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            if (array_key_exists('items', $validated)) {
                $priceList->prices()->delete();
                if (!empty($validated['items'])) {
                    $priceList->prices()->createMany($validated['items']);
                }
            }
        });

        return response()->json([
            'message' => 'تم تحديث قائمة الأسعار بنجاح.',
            'data' => new PriceListResource($priceList->load(['currency', 'prices.product', 'prices.productUnit'])),
        ]);
    }

    /**
     * حذف قائمة أسعار
     */
    public function destroy(PriceList $priceList): JsonResponse
    {
        $this->authorize('delete', $priceList);

        DB::transaction(function () use ($priceList) {
            $priceList->prices()->delete();
            $priceList->delete();
        });

        return response()->json([
            'message' => 'تم حذف قائمة الأسعار بنجاح.',
        ]);
    }
}