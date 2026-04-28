<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\CostCenter;
use App\Modules\Accounting\Services\CostCenterService;
use App\Modules\Accounting\Http\Requests\StoreCostCenterRequest;
use App\Modules\Accounting\Http\Requests\UpdateCostCenterRequest;
use App\Modules\Accounting\Http\Resources\CostCenterResource;
use Illuminate\Http\JsonResponse;

class CostCenterController extends Controller
{
    public function __construct(
        protected CostCenterService $service
    ) {
        // 🌟 توحيد الحماية: تفعيل الـ Policies لجميع مسارات هذا المتحكم بضغطة واحدة
        // (تأكد أن المتغير في الـ Route يسمى cost_center)
        $this->authorizeResource(CostCenter::class, 'cost_center');
    }

    public function index(): JsonResponse
    {
        // جلب البيانات كشجرة مرتبة (مثالية لواجهات الـ Tree-view في Vue)
        $centers = CostCenter::defaultOrder()->get()->toTree();

        return response()->json([
            'data' => CostCenterResource::collection($centers)
        ]);
    }

    public function show(CostCenter $costCenter): JsonResponse
    {
        // تحميل الأبناء المباشرين عند عرض تفاصيل مركز محدد
        $costCenter->load('children');

        return response()->json([
            'data' => new CostCenterResource($costCenter)
        ]);
    }

    public function store(StoreCostCenterRequest $request): JsonResponse
    {
        // السيرفس ستتكفل بتوليد كود المركز آلياً (1, 101, 10101...)
        $center = $this->service->createCostCenter($request->validated());

        return response()->json([
            'message' => 'تم إنشاء مركز التكلفة بنجاح',
            'data'    => new CostCenterResource($center),
        ], 201);
    }

    public function update(UpdateCostCenterRequest $request, CostCenter $costCenter): JsonResponse
    {
        // السيرفس تمنع تعديل الكود المحاسبي آلياً لحماية الشجرة
        $updatedCenter = $this->service->updateCostCenter($costCenter, $request->validated());

        return response()->json([
            'message' => 'تم تحديث بيانات مركز التكلفة بنجاح',
            'data'    => new CostCenterResource($updatedCenter),
        ]);
    }

    public function destroy(CostCenter $costCenter): JsonResponse
    {
        // السيرفس ستفحص الارتباطات (فروع، قيود، أقسام HR) قبل الحذف
        $this->service->deleteCostCenter($costCenter);

        return response()->json([
            'message' => 'تم حذف مركز التكلفة بنجاح',
        ]);
    }
}
