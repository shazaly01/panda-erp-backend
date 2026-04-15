<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\OvertimePolicy;
use App\Modules\HR\Http\Requests\OvertimePolicy\StoreOvertimePolicyRequest;
use App\Modules\HR\Http\Requests\OvertimePolicy\UpdateOvertimePolicyRequest;
use App\Modules\HR\Http\Resources\OvertimePolicyResource;
use App\Modules\HR\Policies\OvertimePolicyPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OvertimePolicyController extends Controller
{
    public function __construct()
    {
        // حماية المتحكم بالكامل
        $this->middleware('auth:sanctum');
    }

    /**
     * عرض قائمة سياسات العمل الإضافي
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', OvertimePolicy::class);

        // جلب السياسات (استخدمنا paginate لتتوافق مع شاشات Data Table)
        $policies = OvertimePolicy::latest()->paginate(15);

        return OvertimePolicyResource::collection($policies);
    }

    /**
     * حفظ سياسة جديدة
     */
    public function store(StoreOvertimePolicyRequest $request): JsonResponse
    {
        $this->authorize('create', OvertimePolicy::class);

        $policy = OvertimePolicy::create($request->validated());

        return response()->json([
            'message' => 'تم إنشاء سياسة العمل الإضافي بنجاح.',
            'data' => new OvertimePolicyResource($policy)
        ], 201);
    }

    /**
     * عرض سياسة محددة
     */
    public function show(OvertimePolicy $overtimePolicy): JsonResponse
    {
        $this->authorize('view', $overtimePolicy);

        return response()->json([
            'data' => new OvertimePolicyResource($overtimePolicy)
        ]);
    }

    /**
     * تحديث سياسة
     */
    public function update(UpdateOvertimePolicyRequest $request, OvertimePolicy $overtimePolicy): JsonResponse
    {
        $this->authorize('update', $overtimePolicy);

        $overtimePolicy->update($request->validated());

        return response()->json([
            'message' => 'تم تحديث سياسة العمل الإضافي بنجاح.',
            'data' => new OvertimePolicyResource($overtimePolicy)
        ]);
    }

    /**
     * حذف سياسة
     */
    public function destroy(OvertimePolicy $overtimePolicy): JsonResponse
    {
        $this->authorize('delete', $overtimePolicy);

        // التأكد من عدم ارتباط السياسة بأي عقود قبل الحذف (حماية لسلامة البيانات)
        if ($overtimePolicy->contracts()->exists()) {
            return response()->json([
                'message' => 'لا يمكن حذف هذه السياسة لوجود عقود مرتبطة بها.'
            ], 422);
        }

        $overtimePolicy->delete();

        return response()->json([
            'message' => 'تم حذف سياسة العمل الإضافي بنجاح.'
        ]);
    }
}
