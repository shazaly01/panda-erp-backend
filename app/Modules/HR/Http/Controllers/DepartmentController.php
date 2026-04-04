<?php

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\Department;
use App\Modules\HR\Http\Resources\DepartmentResource;
use App\Modules\HR\Http\Requests\Department\StoreDepartmentRequest;
use App\Modules\HR\Http\Requests\Department\UpdateDepartmentRequest;
use Illuminate\Http\JsonResponse;

class DepartmentController extends Controller
{
    public function __construct()
    {
        // تفعيل السياسات (Policies) تلقائياً لكل الدوال
        $this->authorizeResource(Department::class, 'department');
    }

    public function index(): JsonResponse
    {
        // ميزة رائعة: جلب الإدارات كشجرة (Tree) متداخلة
        // هذا يسهل عرضها في الفرونت إند (إدارة -> قسم -> وحدة)
        $departments = Department::defaultOrder()->get()->toTree();

        return response()->json([
            'data' => DepartmentResource::collection($departments)
        ]);
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $department = Department::create($request->validated());

        return response()->json([
            'message' => 'تم إنشاء الإدارة بنجاح',
            'data' => new DepartmentResource($department),
        ], 201);
    }

    public function show(Department $department): JsonResponse
    {
        // نحمل الأبناء (children) والأب (parent) للعرض
        $department->load(['children', 'parent']);

        return response()->json([
            'data' => new DepartmentResource($department)
        ]);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): JsonResponse
    {
        $department->update($request->validated());

        return response()->json([
            'message' => 'تم تحديث البيانات بنجاح',
            'data' => new DepartmentResource($department),
        ]);
    }

    public function destroy(Department $department): JsonResponse
    {
        // لا نحذف الإدارة إذا كان بها موظفين (حماية البيانات)
        if ($department->employees()->exists()) {
            return response()->json([
                'message' => 'لا يمكن حذف الإدارة لوجود موظفين مرتبطين بها.'
            ], 422);
        }

        $department->delete();

        return response()->json(['message' => 'تم الحذف بنجاح']);
    }
}
