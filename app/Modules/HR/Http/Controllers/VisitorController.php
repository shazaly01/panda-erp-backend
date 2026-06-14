<?php

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\Visitor;
use App\Modules\HR\Services\VisitorService;
use App\Modules\HR\Http\Resources\VisitorResource;
use App\Modules\HR\Http\Requests\Visitor\StoreVisitorRequest;
use App\Modules\HR\Http\Requests\Visitor\UpdateVisitorRequest;
use App\Modules\HR\Http\Requests\Visitor\GateActionRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VisitorController extends Controller
{
    protected VisitorService $visitorService;

    public function __construct(VisitorService $visitorService)
    {
        $this->visitorService = $visitorService;
    }

    /**
     * عرض قائمة الزوار مع الفلاتر (لوحة التحكم وشاشة الأمن).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Visitor::class);

        $visitors = $this->visitorService->getAllVisitors($request->all());

        return VisitorResource::collection($visitors);
    }

    /**
     * تسجيل زائر جديد من داخل لوحة التحكم (الاستقبال).
     */
    public function store(StoreVisitorRequest $request): VisitorResource
    {
        $this->authorize('create', Visitor::class);

        $visitor = $this->visitorService->registerVisitor($request->validated());

        return new VisitorResource($visitor);
    }

    /**
     * تسجيل زائر جديد عبر الرابط الخارجي العام (الخدمة الذاتية).
     */
    public function publicStore(StoreVisitorRequest $request): VisitorResource
    {
        $visitor = $this->visitorService->registerVisitor($request->validated());

        return new VisitorResource($visitor);
    }

    /**
     * البحث الآمن والمقيد عن الموظفين المستضيفين (Autocomplete).
     */
    public function searchHosts(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['required', 'string', 'min:3']
        ]);

        $hosts = $this->visitorService->searchSecureHosts($request->search);

        return response()->json($hosts);
    }

    /**
     * عرض تفاصيل زائر محدد.
     */
    public function show(Visitor $visitor): VisitorResource
    {
        $this->authorize('view', $visitor);

        return new VisitorResource($visitor->load(['employee', 'gatekeeper']));
    }

    /**
     * تحديث بيانات الزائر.
     */
    public function update(UpdateVisitorRequest $request, Visitor $visitor): VisitorResource
    {
        $this->authorize('update', $visitor);

        $updatedVisitor = $this->visitorService->updateVisitor($visitor, $request->validated());

        return new VisitorResource($updatedVisitor);
    }

    /**
     * حذف سجل الزائر (Soft Delete).
     */
    public function destroy(Visitor $visitor): JsonResponse
    {
        $this->authorize('delete', $visitor);

        $this->visitorService->deleteVisitor($visitor);

        return response()->json(['message' => 'تم حذف سجل الزائر بنجاح.']);
    }

    /**
     * تسجيل دخول الزائر عند البوابة (Check-in).
     */
    public function checkIn(GateActionRequest $request): VisitorResource
    {
        $identifier = $request->only(['id', 'qr_token']);

        // جلب السجل للتحقق من الصلاحيات الأمنية عبر الـ Policy أولاً
        $visitor = Visitor::when(!empty($identifier['id']), function ($q) use ($identifier) {
            return $q->where('id', $identifier['id']);
        })->unless(!empty($identifier['id']), function ($q) use ($identifier) {
            return $q->where('qr_token', $identifier['qr_token']);
        })->firstOrFail();

        $this->authorize('checkIn', $visitor);

        $updatedVisitor = $this->visitorService->processCheckIn($identifier, $request->user()->id);

        return new VisitorResource($updatedVisitor);
    }

    /**
     * تسجيل خروج الزائر عند البوابة (Check-out).
     */
    public function checkOut(GateActionRequest $request): VisitorResource
    {
        $identifier = $request->only(['id', 'qr_token']);

        $visitor = Visitor::when(!empty($identifier['id']), function ($q) use ($identifier) {
            return $q->where('id', $identifier['id']);
        })->unless(!empty($identifier['id']), function ($q) use ($identifier) {
            return $q->where('qr_token', $identifier['qr_token']);
        })->firstOrFail();

        $this->authorize('checkOut', $visitor);

        $updatedVisitor = $this->visitorService->processCheckOut($identifier, $request->user()->id);

        return new VisitorResource($updatedVisitor);
    }


    /**
     * جلب حالة الزائر اللحظية لبوابات الأمن عبر التوكن المشفر.
     */
    public function checkStatus(string $token): \Illuminate\Http\JsonResponse
    {
        $statusData = $this->visitorService->getVisitorStatusForGate($token);

        return response()->json($statusData);
    }
}
