<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\PayGroup;
use App\Modules\HR\Http\Resources\PayGroupResource;
use App\Modules\HR\Http\Requests\PayGroup\StorePayGroupRequest;
use App\Modules\HR\Http\Requests\PayGroup\UpdatePayGroupRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayGroupController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(PayGroup::class, 'pay_group');
    }

    public function index(Request $request): JsonResponse
    {
        $query = PayGroup::query();

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $groups = $query->orderBy('name')->get();

        return response()->json(PayGroupResource::collection($groups));
    }

    public function store(StorePayGroupRequest $request): JsonResponse
    {
        $group = PayGroup::create($request->validated());

        return response()->json([
            'message' => 'تم إنشاء مجموعة الدفع بنجاح',
            'data' => new PayGroupResource($group),
        ], 201);
    }

    public function show(PayGroup $payGroup): JsonResponse
    {
        return response()->json(new PayGroupResource($payGroup));
    }

    public function update(UpdatePayGroupRequest $request, PayGroup $payGroup): JsonResponse
    {
        $payGroup->update($request->validated());

        return response()->json([
            'message' => 'تم تحديث مجموعة الدفع بنجاح',
            'data' => new PayGroupResource($payGroup),
        ]);
    }

    public function destroy(PayGroup $payGroup): JsonResponse
    {
        if ($payGroup->contracts()->exists() || $payGroup->payPeriods()->exists()) {
            return response()->json(['message' => 'لا يمكن حذف المجموعة لارتباطها بعقود أو فترات مالية'], 422);
        }

        $payGroup->delete();
        return response()->json(['message' => 'تم حذف مجموعة الدفع بنجاح']);
    }
}
