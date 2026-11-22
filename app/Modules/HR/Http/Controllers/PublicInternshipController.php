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
use Illuminate\Support\Facades\Cache;

class PublicInternshipController extends Controller
{
    protected InternshipService $internshipService;

    public function __construct(InternshipService $internshipService)
    {
        $this->internshipService = $internshipService;
    }

    /**
     * 0. جلب حالة فتح استقبال الطلبات للبوابة الخارجية (تدريب وتوظيف)
     */
    public function registrationStatus(): JsonResponse
    {
        $internshipOpen = (bool) Cache::get('hr_internship_applications_open', true);
        $jobOpen = (bool) Cache::get('hr_job_applications_open', true);

        return response()->json([
            'is_open' => $internshipOpen,
            'internship_open' => $internshipOpen,
            'job_open' => $jobOpen,
        ], 200);
    }

    /**
     * 1. استقبال طلبات التدريب أو التوظيف الجديدة من الرابط العام
     * يعيد كود المتابعة المكون من 5 أرقام فوراً للمتقدم لحفظه.
     */
    public function store(StorePublicApplicationRequest $request): JsonResponse
    {
        $application = $this->internshipService->storePublicApplication(
            $request->validated(),
            $request->file('photo'),
            $request->file('cv')
        );

        return response()->json([
            'message' => 'تم إرسال طلبك بنجاح. يرجى الاحتفاظ بكود المتابعة الخاص بك لدخول بوابة المتابعة لاحقاً.',
            'data' => new InternshipApplicationResource($application)
        ], 201);
    }

    /**
     * 2. بوابة تسجيل الدخول للمتابعة باستخدام رقم الهاتف وكود المتابعة
     * تعيد بيانات الطلب بالكامل مع علم الجاهزية للتعديل (can_edit) وموعد المعاينة والباركود إن وجد.
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
     * 3. تحديث وتعديل بيانات الطلب بواسطة المتقدم الخارجي
     * مغلق ومحمي أمنياً داخل الخدمة في حال تم نقل الطلب للمعاينة أو قبوله أو رفضه من المشرف.
     */
    public function update(UpdatePublicApplicationRequest $request, int $id): JsonResponse
    {
        $application = $this->internshipService->updatePublicApplication(
            $id,
            $request->validated(),
            $request->file('photo'),
            $request->file('cv')
        );

        return response()->json([
            'message' => 'تم تحديث بيانات طلبك بنجاح.',
            'data' => new InternshipApplicationResource($application)
        ], 200);
    }
}