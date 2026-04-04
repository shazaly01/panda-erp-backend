<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Box;
use App\Modules\Accounting\Services\TreasuryService; // <--- لاحظ استدعاء السيرفس
use App\Modules\Accounting\Http\Requests\StoreBoxRequest;
use App\Modules\Accounting\Http\Requests\UpdateBoxRequest;
use App\Modules\Accounting\Http\Resources\BoxResource;
use Illuminate\Http\JsonResponse;

class BoxController extends Controller
{
    // حقن السيرفس في الكنترولر (Dependency Injection)
    public function __construct(protected TreasuryService $treasuryService)
    {
        // تفعيل الحماية (Policies)
        $this->authorizeResource(Box::class, 'box');
    }

    /**
     * عرض كل الخزائن
     */
    public function index(): JsonResponse
    {
        // نحمل علاقة الحساب والعملة لتقليل الاستعلامات (Eager Loading)
        $boxes = Box::with(['account', 'currency'])->get();

        return response()->json([
            'data' => BoxResource::collection($boxes)
        ]);
    }

    /**
     * إضافة خزينة جديدة
     */
    public function store(StoreBoxRequest $request): JsonResponse
    {
        // هنا السحر: نرسل البيانات للسيرفس لتقوم بإنشاء الحساب والخزينة معاً
        $box = $this->treasuryService->createBox($request->validated());

        // نعيد تحميل العلاقات لنعرضها في الرد
        $box->load(['account', 'currency']);

        return response()->json([
            'message' => 'تم إنشاء الخزينة والحساب المالي المرتبط بها بنجاح',
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
        // السيرفس تتكفل بتحديث اسم الحساب المالي أيضاً ليطابق اسم الخزينة
        $updatedBox = $this->treasuryService->updateBox($box, $request->validated());

        return response()->json([
            'message' => 'تم تحديث بيانات الخزينة بنجاح',
            'data'    => new BoxResource($updatedBox)
        ]);
    }

    /**
     * حذف الخزينة
     */
    public function destroy(Box $box): JsonResponse
    {
        // السيرفس تتكفل بحذف الحساب المالي المرتبط (بعد التحقق من عدم وجود قيود)
        // قد ترمي السيرفس Exception إذا كان هناك قيود، وسيتعامل معها لارافيل تلقائياً
        $this->treasuryService->deleteBox($box);

        return response()->json([
            'message' => 'تم حذف الخزينة وحسابها المالي بنجاح'
        ]);
    }
}
