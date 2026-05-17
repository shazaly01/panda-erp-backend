<?php

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\Department;
use App\Modules\HR\Http\Resources\DepartmentResource;
use App\Modules\HR\Http\Requests\Department\StoreDepartmentRequest;
use App\Modules\HR\Http\Requests\Department\UpdateDepartmentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DepartmentController extends Controller
{
    public function __construct()
    {
        // تفعيل السياسات (Policies) تلقائياً لكل الدوال
        $this->authorizeResource(Department::class, 'department');
    }

    public function index(): JsonResponse
    {
        // جلب الإدارات كشجرة متداخلة مع تحميل المشرفين لكل قسم كـ Eager Loading
        $departments = Department::defaultOrder()->with('supervisors')->get()->toTree();

        return response()->json([
            'data' => DepartmentResource::collection($departments)
        ]);
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        // تنفيذ العملية داخل Transaction لضمان حفظ القسم والمشرفين معاً أو تراجع النظام بالكامل
        $department = DB::transaction(function () use ($request) {
            $dept = Department::create($request->validated());

            if ($request->has('supervisor_ids')) {
                $dept->supervisors()->sync($request->input('supervisor_ids'));
            }

            return $dept;
        });

        $department->load('supervisors');

        return response()->json([
            'message' => 'تم إنشاء الإدارة بنجاح',
            'data' => new DepartmentResource($department),
        ], 201);
    }

    public function show(Department $department): JsonResponse
    {
        // تحميل الأبناء والأب والمشرفين للعرض الكامل
        $department->load(['children', 'parent', 'supervisors']);

        return response()->json([
            'data' => new DepartmentResource($department)
        ]);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): JsonResponse
    {
        DB::transaction(function () use ($request, $department) {
            $department->update($request->validated());

            if ($request->has('supervisor_ids')) {
                $department->supervisors()->sync($request->input('supervisor_ids'));
            }
        });

        $department->load('supervisors');

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
