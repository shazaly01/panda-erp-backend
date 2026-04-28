<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Box;
use App\Modules\Accounting\Services\TreasuryService;
// 🌟 تم تصحيح المسارات (إزالة التخمين الخاص بمجلد Box)
use App\Modules\Accounting\Http\Requests\StoreBoxRequest;
use App\Modules\Accounting\Http\Requests\UpdateBoxRequest;
use App\Modules\Accounting\Http\Resources\BoxResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoxController extends Controller
{
    public function __construct(protected TreasuryService $treasuryService)
    {
        $this->authorizeResource(Box::class, 'box');
    }

    public function index(Request $request): JsonResponse
    {
        $query = Box::with(['account', 'currency']);

        // 1. فلترة البحث (البحث في اسم الخزينة تشغيلياً أو كود الحساب المالي المرتبط بها)
        $query->when($request->search, function ($q, $search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhereHas('account', function ($acc) use ($search) {
                        $acc->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            });
        });

        // 2. فلترة الحالة
        $query->when($request->filled('status'), function ($q) use ($request) {
            $q->where('is_active', $request->status);
        });

        // 3. فلترة الفرع (مركز التكلفة) إن وجد
        $query->when($request->filled('branch_id'), function ($q) use ($request) {
            $q->where('branch_id', $request->branch_id);
        });

        $boxes = $query->latest('id')->paginate(20);

        return BoxResource::collection($boxes)->response();
    }

    /**
     * إضافة خزينة جديدة (إنشاء تشغيلي + مالي آلي)
     */
    public function store(StoreBoxRequest $request): JsonResponse
    {
        // نرسل البيانات التشغيلية فقط، والسيرفس سيتولى توليد الحساب المالي تحت 11101
        $box = $this->treasuryService->createBox($request->validated());

        $box->load(['account', 'currency']);

        return response()->json([
            'message' => 'تم إنشاء الخزينة وتوليد حسابها المالي آلياً بنجاح.',
            'data'    => new BoxResource($box)
        ], 201);
    }

    /**
     * عرض خزينة محددة
     */
    public function show(Box $box): JsonResponse
    {
        $box->load(['account', 'currency']);

        return response()->json([
            'data' => new BoxResource($box)
        ]);
    }

    /**
     * تحديث بيانات الخزينة
     */
    public function update(UpdateBoxRequest $request, Box $box): JsonResponse
    {
        // السيرفس سيقوم بتحديث اسم الخزينة، ومزامنة التعديل مع اسم الحساب المالي في الشجرة
        $updatedBox = $this->treasuryService->updateBox($box, $request->validated());

        return response()->json([
            'message' => 'تم تحديث بيانات الخزينة بنجاح.',
            'data'    => new BoxResource($updatedBox->load(['account', 'currency']))
        ]);
    }

    /**
     * حذف الخزينة
     */
    public function destroy(Box $box): JsonResponse
    {
        // السيرفس سيفحص وجود قيود، وإن كانت آمنة سيحذف الخزينة وحسابها المالي معاً
        $this->treasuryService->deleteBox($box);

        return response()->json([
            'message' => 'تم حذف الخزينة وحسابها المالي بنجاح.'
        ]);
    }
}
