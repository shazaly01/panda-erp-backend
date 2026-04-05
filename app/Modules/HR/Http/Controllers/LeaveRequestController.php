<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\LeaveRequest;
use App\Modules\HR\Http\Requests\Leave\StoreLeaveRequest;
use App\Modules\HR\Http\Requests\Leave\UpdateLeaveRequest;
use App\Modules\HR\Http\Resources\LeaveRequestResource; // عدلنا المسار حسب طلبك السابق
use App\Modules\HR\Services\LeaveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class LeaveRequestController extends Controller
{
    // حقن (Inject) الخدمة التي برمجناها مسبقاً
    public function __construct(private readonly LeaveService $leaveService)
    {
    }

    /**
     * عرض قائمة الإجازات
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', LeaveRequest::class);

        $user = Auth::user();
        $query = LeaveRequest::with(['employee', 'leaveType', 'approver']);

        // إذا كان موظفاً عادياً (ESS)، نعرض له إجازاته فقط
        if (!$user->hasPermissionTo('hr.leaves.view') && $user->employee_id) {
            $query->where('employee_id', $user->employee_id);
        }

        return LeaveRequestResource::collection($query->latest()->paginate(15));
    }

    /**
     * إنشاء طلب إجازة جديد
     */
    public function store(StoreLeaveRequest $request): LeaveRequestResource
    {
        $this->authorize('create', LeaveRequest::class);

        $data = $request->validated();
        $user = Auth::user();

        // (الخدمة الذاتية): إذا كان موظفاً عادياً، نجبر النظام على استخدام رقمه الوظيفي
        // لمنعه من تقديم طلب باسم زميله، حتى لو تلاعب بالـ Payload
        if (!$user->hasPermissionTo('hr.leaves.manage')) {
            $data['employee_id'] = $user->employee_id;
        }

        // نحسب عدد الأيام آلياً بين البداية والنهاية (+1 لكي يحسب يوم البداية كـ يوم كامل)
        // ملاحظة: في الأنظمة المعقدة يتم استثناء العطلات الرسمية هنا
        $start = \Carbon\Carbon::parse($data['start_date']);
        $end = \Carbon\Carbon::parse($data['end_date']);
        $data['total_days'] = $start->diffInDays($end) + 1;

        $leaveRequest = LeaveRequest::create($data);

        return new LeaveRequestResource($leaveRequest->load(['employee', 'leaveType']));
    }

    /**
     * عرض طلب إجازة محدد
     */
    public function show(LeaveRequest $leaveRequest): LeaveRequestResource
    {
        $this->authorize('view', $leaveRequest);

        return new LeaveRequestResource($leaveRequest->load(['employee', 'leaveType', 'approver']));
    }

    /**
     * تعديل طلب الإجازة (مسموح فقط إذا كان معلقاً)
     */
    public function update(UpdateLeaveRequest $request, LeaveRequest $leaveRequest): LeaveRequestResource
    {
        $this->authorize('update', $leaveRequest);

        $data = $request->validated();

        // إعادة حساب الأيام إذا تم تعديل التواريخ
        if (isset($data['start_date']) || isset($data['end_date'])) {
            $start = isset($data['start_date']) ? \Carbon\Carbon::parse($data['start_date']) : \Carbon\Carbon::parse($leaveRequest->start_date);
            $end = isset($data['end_date']) ? \Carbon\Carbon::parse($data['end_date']) : \Carbon\Carbon::parse($leaveRequest->end_date);
            $data['total_days'] = $start->diffInDays($end) + 1;
        }

        $leaveRequest->update($data);

        return new LeaveRequestResource($leaveRequest->load(['employee', 'leaveType']));
    }

    /**
     * حذف أو إلغاء الطلب
     */
    public function destroy(LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('delete', $leaveRequest);

        $leaveRequest->delete();

        return response()->json(['message' => 'تم إلغاء طلب الإجازة بنجاح.'], 200);
    }

    /**
     * [عملية إدارية]: اعتماد طلب الإجازة وخصم الرصيد
     */
    public function approve(LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('approve', $leaveRequest);

        try {
            // هنا نستدعي (العقل المدبر) الذي كتبناه في طبقة الـ Services لخصم الرصيد بأمان
            $this->leaveService->approveLeaveRequest($leaveRequest, Auth::id());

            return response()->json([
                'message' => 'تم اعتماد الإجازة وخصم الرصيد بنجاح.',
                'data' => new LeaveRequestResource($leaveRequest->fresh(['approver']))
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
