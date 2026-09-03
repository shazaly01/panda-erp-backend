<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Http\Requests\Categories\StoreCategoryRequest;
use App\Modules\Inventory\Http\Requests\Categories\UpdateCategoryRequest;
use App\Modules\Inventory\Http\Resources\CategoryResource;
use App\Modules\Inventory\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    /**
     * عرض قائمة التصنيفات
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Category::class);

        $categories = Category::query()
            ->with(['parent', 'children'])
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
            ->when($request->has('is_parent'), function ($q) use ($request) {
                if ($request->boolean('is_parent')) {
                    $q->whereNull('parent_id');
                }
            })
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return CategoryResource::collection($categories);
    }

    /**
     * إنشاء تصنيف جديد
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', Category::class);

        $category = Category::create($request->validated());

        return response()->json([
            'message' => 'تم إنشاء التصنيف بنجاح.',
            'data' => new CategoryResource($category->load(['parent', 'children'])),
        ], 201);
    }

    /**
     * عرض تفاصيل تصنيف معين
     */
    public function show(Category $category): CategoryResource
    {
        $this->authorize('view', $category);

        return new CategoryResource($category->load(['parent', 'children']));
    }

    /**
     * تعديل تصنيف
     */
    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $this->authorize('update', $category);

        $category->update($request->validated());

        return response()->json([
            'message' => 'تم تحديث التصنيف بنجاح.',
            'data' => new CategoryResource($category->load(['parent', 'children'])),
        ]);
    }

    /**
     * حذف تصنيف
     */
    public function destroy(Category $category): JsonResponse
    {
        $this->authorize('delete', $category);

        $category->delete();

        return response()->json([
            'message' => 'تم حذف التصنيف بنجاح.',
        ]);
    }
}