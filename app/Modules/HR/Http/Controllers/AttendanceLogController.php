<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\AttendanceLog;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Http\Requests\Attendance\StoreAttendanceLogRequest;
use App\Modules\HR\Http\Requests\Attendance\UpdateAttendanceLogRequest;
use App\Modules\HR\Http\Resources\AttendanceLogResource; // المسار المباشر
use App\Modules\HR\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class AttendanceLogController extends Controller
{
    // حقن خدمة الحضور المسؤولة عن العمليات الحسابية
    public function __construct(private readonly AttendanceService $attendanceService)
    {
    }

    /**
     * عرض سجلات الحضور
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AttendanceLog::class);

        $user = Auth::user();
        $query = AttendanceLog::with(['employee', 'shift']);

        // إذا كان الموظف يتصفح النظام (ESS)، يرى سجلاته فقط
        if (!$user->hasPermissionTo('hr.attendance.view') && $user->employee_id) {
            $query->where('employee_id', $user->employee_id);
        }

        // ترتيب تنازلي حسب التاريخ ليعرض أحدث السجلات أولاً
        return AttendanceLogResource::collection($query->orderByDesc('date')->paginate(30));
    }

    /**
     * إدخال سجل حضور يدوي (صلاحية للإدارة فقط)
     */
    public function store(StoreAttendanceLogRequest $request): JsonResponse
    {
        $this->authorize('create', AttendanceLog::class);

        $data = $request->validated();
        $employee = Employee::findOrFail($data['employee_id']);

        try {
            // نمرر البيانات للـ Service وهي ستتكفل بجلب الوردية وحساب التأخير والإضافي
            $log = $this->attendanceService->processDailyAttendance(
                $employee,
                $data['date'],
                $data['check_in'] ?? null,
                $data['check_out'] ?? null
            );

            // في حال تم تحديد حالة معينة يدوياً (مثل: on_leave) نقوم بتحديثها فوراً
            if (isset($data['status']) && $data['status'] !== $log->status) {
                $log->update(['status' => $data['status']]);
            }

            return response()->json([
                'message' => 'تم تسجيل الحضور وحساب التأخيرات بنجاح.',
                'data' => new AttendanceLogResource($log->load(['employee', 'shift']))
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * عرض سجل يوم محدد
     */
    public function show(AttendanceLog $attendanceLog): AttendanceLogResource
    {
        $this->authorize('view', $attendanceLog);

        return new AttendanceLogResource($attendanceLog->load(['employee', 'shift']));
    }

    /**
     * تعديل سجل الحضور (مثال: نسي الموظف البصمة وقام الـ HR بإضافتها لاحقاً)
     */
    public function update(UpdateAttendanceLogRequest $request, AttendanceLog $attendanceLog): JsonResponse
    {
        $this->authorize('update', $attendanceLog);

        $data = $request->validated();

        try {
            $checkIn = $data['check_in'] ?? $attendanceLog->check_in;
            $checkOut = $data['check_out'] ?? $attendanceLog->check_out;

            // نعيد تمرير التحديث للـ Service لكي تقوم "بإعادة حساب" دقائق التأخير والإضافي بناءً على الوقت الجديد
            $updatedLog = $this->attendanceService->processDailyAttendance(
                $attendanceLog->employee,
                $attendanceLog->date->format('Y-m-d'),
                $checkIn,
                $checkOut
            );

            if (isset($data['status'])) {
                $updatedLog->update(['status' => $data['status']]);
            }

            return response()->json([
                'message' => 'تم تحديث السجل وإعادة حساب الأوقات بنجاح.',
                'data' => new AttendanceLogResource($updatedLog->load(['employee', 'shift']))
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * حذف السجل (عملية حساسة جداً)
     */
    public function destroy(AttendanceLog $attendanceLog): JsonResponse
    {
        $this->authorize('delete', $attendanceLog);

        $attendanceLog->delete();

        return response()->json(['message' => 'تم حذف سجل الحضور بنجاح.'], 200);
    }
}
