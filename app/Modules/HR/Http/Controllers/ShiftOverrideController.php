<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\ShiftOverride;
use App\Modules\HR\Http\Requests\Schedules\ShiftOverrideRequest;
use App\Modules\HR\Http\Resources\ShiftOverrideResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;

class ShiftOverrideController extends Controller
{
    /**
     * عرض قائمة التجاوزات الفردية
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ShiftOverride::class);

        // تحميل العلاقات مسبقاً لتجنب مشكلة N+1
        $overrides = ShiftOverride::with(['employee', 'originalShift', 'newShift', 'approver'])
            ->latest('date')
            ->get();

        return ShiftOverrideResource::collection($overrides);
    }

    /**
     * إنشاء تجاوز فردي جديد (تبديل وردية)
     */
    public function store(ShiftOverrideRequest $request): ShiftOverrideResource
    {
        $this->authorize('create', ShiftOverride::class);

        $data = $request->validated();

        // التقاط الـ ID للمدير أو موظف الـ HR الذي قام بالعملية آلياً
        $data['approved_by'] = auth()->id();

        $override = ShiftOverride::create($data);

        // إرجاع النتيجة مع تحميل العلاقات للواجهة الأمامية
        return new ShiftOverrideResource($override->load(['employee', 'originalShift', 'newShift', 'approver']));
    }

    /**
     * عرض تفاصيل تجاوز محدد
     */
    public function show(ShiftOverride $shiftOverride): ShiftOverrideResource
    {
        $this->authorize('view', $shiftOverride);

        return new ShiftOverrideResource($shiftOverride->load(['employee', 'originalShift', 'newShift', 'approver']));
    }

    /**
     * تحديث بيانات تجاوز
     */
    public function update(ShiftOverrideRequest $request, ShiftOverride $shiftOverride): ShiftOverrideResource
    {
        $this->authorize('update', $shiftOverride);

        // ملاحظة: لا نحدث approved_by هنا للحفاظ على توثيق من قام بالاعتماد الأول،
        // إلا إذا كان هناك متطلب أعمال (Business Rule) يقتضي ذلك.
        $shiftOverride->update($request->validated());

        return new ShiftOverrideResource($shiftOverride->load(['employee', 'originalShift', 'newShift', 'approver']));
    }

    /**
     * حذف تجاوز
     */
    public function destroy(ShiftOverride $shiftOverride): JsonResponse
    {
        $this->authorize('delete', $shiftOverride);

        $shiftOverride->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'تم حذف التجاوز بنجاح.'
        ]);
    }
}
