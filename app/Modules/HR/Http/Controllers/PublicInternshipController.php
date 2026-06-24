<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Http\Requests\Employee\StorePublicApplicationRequest;
use App\Modules\HR\Http\Requests\Employee\CheckApplicationTrackingRequest;
use App\Modules\HR\Http\Requests\Employee\UpdatePublicApplicationRequest;
use App\Modules\HR\Http\Resources\InternshipApplicationResource;
use App\Modules\HR\Services\InternshipService;
use Illuminate\Http\JsonResponse;

class PublicInternshipController extends Controller
{
    protected InternshipService $internshipService;

    public function __construct(InternshipService $internshipService)
    {
        $this->internshipService = $internshipService;
    }

    /**
     * 1. استقبال طلبات التدريب الجديدة من الرابط العام (التقاط صورة الكاميرا الحية)
     * يعيد كود المتابعة المكون من 5 أرقام فوراً للمتقدم لحفظه.
     */
    public function store(StorePublicApplicationRequest $request): JsonResponse
    {
        $application = $this->internshipService->storePublicApplication(
            $request->validated(),
            $request->file('photo')
        );

        return response()->json([
            'message' => 'تم إرسال طلبك للتدريب بنجاح. يرجى الاحتفاظ بكود المتابعة الخاص بك لدخول بوابة المتابعة لاحقاً.',
            'data' => new InternshipApplicationResource($application)
        ], 201);
    }

    /**
     * 2. بوابة تسجيل الدخول للمتابعة باستخدام رقم الهاتف وكود المتابعة
     * تعيد بيانات الطلب بالكامل مع علم الجاهزية للتعديل (can_edit) والباركود إن وجد.
     */
    public function track(CheckApplicationTrackingRequest $request): JsonResponse
    {
        $application = $this->internshipService->trackApplication(
            (string) $request->input('phone'),
            (string) $request->input('tracking_code')
        );

        return response()->json([
            'message' => 'تم التحقق من البيانات بنجاح والدخول لملف المتابعة.',
            'data' => new InternshipApplicationResource($application)
        ], 200);
    }

    /**
     * 3. تحديث وتعديل بيانات الطلب بواسطة المتدرب الخارجي
     * مغلق ومحمي أمنياً داخل الخدمة في حال تم قبول الطلب أو رفضه من المشرف.
     */
    public function update(UpdatePublicApplicationRequest $request, int $id): JsonResponse
    {
        $application = $this->internshipService->updatePublicApplication(
            $id,
            $request->validated(),
            $request->file('photo')
        );

        return response()->json([
            'message' => 'تم تحديث بيانات طلبك بنجاح.',
            'data' => new InternshipApplicationResource($application)
        ], 200);
    }
}
