<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\LeaveRequest;
use App\Modules\HR\Http\Requests\Leave\StoreLeaveRequest;
use App\Modules\HR\Http\Requests\Leave\UpdateLeaveRequest;
use App\Modules\HR\Http\Resources\LeaveRequestResource;
use App\Modules\HR\Services\LeaveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class LeaveRequestController extends Controller
{
    public function __construct(private readonly LeaveService $leaveService)
    {
        /**
         * تفعيل السياسة (LeaveRequestPolicy)
         * لاحظ أن اسم المتغير في الراوت يجب أن يكون leave_request
         */
        $this->authorizeResource(LeaveRequest::class, 'leave_request');
    }

    /**
     * عرض قائمة الإجازات
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        // تم الفحص تلقائياً عبر LeaveRequestPolicy@viewAny

        $user = Auth::user();
        $query = LeaveRequest::with(['employee', 'leaveType', 'approver']);

        // منطق الخدمة الذاتية (ESS)
        // إذا لم يكن لديه صلاحية إدارة أو عرض الإجازات العامة، يرى طلباته فقط
        if (!$user->can('hr.leaves.manage') && !$user->can('hr.leaves.view') && $user->employee_id) {
            $query->where('employee_id', $user->employee_id);
        }

        // إضافة فلاتر إضافية (حالة الطلب، نوع الإجازة)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return LeaveRequestResource::collection($query->latest()->paginate(15));
    }

    /**
     * إنشاء طلب إجازة جديد
     */
    public function store(StoreLeaveRequest $request): LeaveRequestResource
    {
        // تم الفحص تلقائياً عبر LeaveRequestPolicy@create

        $data = $request->validated();
        $user = Auth::user();

        // حماية إضافية: منع الموظف العادي من تقديم طلب باسم غيره
        if (!$user->can('hr.leaves.manage')) {
            $data['employee_id'] = $user->employee_id;
        }

        // حساب الأيام (Logic مغلف داخل المتحكم أو يفضل نقله للسيرفس لاحقاً)
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
        // تم الفحص تلقائياً عبر LeaveRequestPolicy@view
        return new LeaveRequestResource($leaveRequest->load(['employee', 'leaveType', 'approver']));
    }

    /**
     * تعديل طلب الإجازة
     */
    public function update(UpdateLeaveRequest $request, LeaveRequest $leaveRequest): LeaveRequestResource
    {
        // تم الفحص تلقائياً عبر LeaveRequestPolicy@update

        $data = $request->validated();

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
        // تم الفحص تلقائياً عبر LeaveRequestPolicy@delete
        $leaveRequest->delete();

        return response()->json(['message' => 'تم إلغاء طلب الإجازة بنجاح.'], 200);
    }

    /**
     * اعتماد طلب الإجازة (دالة مخصصة خارج الـ Resource)
     */
    public function approve(LeaveRequest $leaveRequest): JsonResponse
    {
        // هذه الدالة ليست ضمن authorizeResource، لذا نحميها يدوياً بالسياسة
        $this->authorize('approve', $leaveRequest);

        try {
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
