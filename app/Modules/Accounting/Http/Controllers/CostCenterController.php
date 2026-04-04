<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\CostCenter;
use App\Modules\Accounting\Services\CostCenterService;
use App\Modules\Accounting\Http\Requests\StoreCostCenterRequest;
use App\Modules\Accounting\Http\Requests\UpdateCostCenterRequest;
use App\Modules\Accounting\Http\Resources\CostCenterResource;

class CostCenterController extends Controller
{
    public function __construct(
        protected CostCenterService $service
    ) {}

    public function index()
    {
        $this->authorize('viewAny', CostCenter::class);

        // جلب البيانات كشجرة
        $centers = CostCenter::defaultOrder()->get()->toTree();

        return CostCenterResource::collection($centers);
    }

    public function show(CostCenter $costCenter)
    {
        $this->authorize('view', $costCenter);
        $costCenter->load('children');
        return new CostCenterResource($costCenter);
    }

    public function store(StoreCostCenterRequest $request)
    {
        $center = $this->service->createCostCenter($request->validated());

        return response()->json([
            'message' => 'تم إنشاء مركز التكلفة بنجاح',
            'data' => new CostCenterResource($center),
        ], 201);
    }

    public function update(UpdateCostCenterRequest $request, CostCenter $costCenter)
    {
        $updatedCenter = $this->service->updateCostCenter($costCenter, $request->validated());

        return response()->json([
            'message' => 'تم تحديث مركز التكلفة بنجاح',
            'data' => new CostCenterResource($updatedCenter),
        ]);
    }

    public function destroy(CostCenter $costCenter)
    {
        $this->authorize('delete', $costCenter);
        $this->service->deleteCostCenter($costCenter);

        return response()->json([
            'message' => 'تم حذف مركز التكلفة بنجاح',
        ]);
    }
}
