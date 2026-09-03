<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Http\Requests\Products\StoreProductRequest;
use App\Modules\Inventory\Http\Requests\Products\UpdateProductRequest;
use App\Modules\Inventory\Http\Resources\ProductResource;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    /**
     * حقن خدمة الأصناف داخل المتحكم
     */
    public function __construct(
        protected ProductService $productService
    ) {}

    /**
     * عرض قائمة الأصناف المفلترة
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Product::class);

        $query = Product::query()
            ->with([
                'category',
                'units.unit',
                'units.prices.priceList',
                'units.barcodes',
                'reorderRules.warehouse',
            ]);

        $warehouseId = $request->input('store_id') ?? $request->input('warehouse_id');

        if ($warehouseId) {
            $query->withSum([
                'stocks as product_stocks_sum_quantity' => function ($q) use ($warehouseId) {
                    $q->where('warehouse_id', $warehouseId);
                },
            ], 'quantity');
        } else {
            $query->withSum('stocks as product_stocks_sum_quantity', 'quantity');
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhereHas('barcodes', function ($b) use ($search) {
                      $b->where('barcode', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->boolean('all')) {
            $products = $query->latest('id')->get();
        } else {
            $perPage = (int) $request->input('per_page', 15);
            $products = $query->latest('id')->paginate($perPage);
        }

        return ProductResource::collection($products);
    }

    /**
     * إنشاء صنف جديد باستخدام StoreProductRequest
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->productService->createProduct($request->validated());

        return response()->json([
            'message' => 'تم إنشاء الصنف بنجاح',
            'data' => new ProductResource($product),
        ], 201);
    }

    /**
     * عرض تفاصيل صنف محدد
     */
    public function show(Product $product): ProductResource
    {
        $this->authorize('view', $product);

        $product->load([
            'category',
            'units.unit',
            'units.prices.priceList',
            'units.barcodes',
            'reorderRules.warehouse',
        ]);

        return new ProductResource($product);
    }

    /**
     * تحديث بيانات صنف قائم باستخدام UpdateProductRequest
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $updatedProduct = $this->productService->updateProduct($product, $request->validated());

        return response()->json([
            'message' => 'تم تحديث الصنف بنجاح',
            'data' => new ProductResource($updatedProduct),
        ]);
    }

    /**
     * حذف صنف محدد
     */
    public function destroy(Product $product): JsonResponse
    {
        $this->authorize('delete', $product);

        $this->productService->deleteProduct($product);

        return response()->json([
            'message' => 'تم حذف الصنف بنجاح',
        ]);
    }
}