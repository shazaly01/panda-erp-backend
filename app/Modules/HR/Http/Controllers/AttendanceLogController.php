<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\AttendanceLog;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Http\Requests\Attendance\StoreAttendanceLogRequest;
use App\Modules\HR\Http\Requests\Attendance\UpdateAttendanceLogRequest;
use App\Modules\HR\Http\Resources\AttendanceLogResource;
use App\Modules\HR\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class AttendanceLogController extends Controller
{
    /**
     * حقن الخدمة وتفعيل السياسة
     */
    public function __construct(private readonly AttendanceService $attendanceService)
    {
        // تفعيل السياسة (AttendanceLogPolicy)
        // ملاحظة: المتغير في المسار (Route) يجب أن يكون attendance_log
        $this->authorizeResource(AttendanceLog::class, 'attendance_log');
    }

    /**
     * عرض سجلات الحضور
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        // تم الفحص تلقائياً عبر AttendanceLogPolicy@viewAny

        $user = Auth::user();
        $query = AttendanceLog::with(['employee', 'shift']);

        // فلترة للبحث (اختياري حسب الحاجة)
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('date')) {
            $query->where('date', $request->date);
        }

        // منطق الخدمة الذاتية (ESS): إذا لم يكن مديراً، يرى سجلاته فقط
        // ملاحظة: نتحقق من الصلاحية 'hr.attendance.manage' بدلاً من view لضمان الفصل
        if (!$user->can('hr.attendance.manage') && $user->employee_id) {
            $query->where('employee_id', $user->employee_id);
        }

        return AttendanceLogResource::collection($query->orderByDesc('date')->paginate(30));
    }

    /**
     * إدخال سجل حضور يدوي
     */
    public function store(StoreAttendanceLogRequest $request): JsonResponse
    {
        // تم الفحص تلقائياً عبر AttendanceLogPolicy@create

        $data = $request->validated();
        $employee = Employee::findOrFail($data['employee_id']);

        try {
            $log = $this->attendanceService->processDailyAttendance(
                $employee,
                $data['date'],
                $data['check_in'] ?? null,
                $data['check_out'] ?? null
            );

            // تحديث الحالة يدوياً إذا طُلب ذلك
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
        // تم الفحص تلقائياً عبر AttendanceLogPolicy@view
        return new AttendanceLogResource($attendanceLog->load(['employee', 'shift']));
    }

    /**
     * تعديل سجل حضور
     */
    public function update(UpdateAttendanceLogRequest $request, AttendanceLog $attendanceLog): JsonResponse
    {
        // تم الفحص تلقائياً عبر AttendanceLogPolicy@update

        $data = $request->validated();

        try {
            $checkIn = $data['check_in'] ?? $attendanceLog->check_in;
            $checkOut = $data['check_out'] ?? $attendanceLog->check_out;

            // إعادة الحساب بناءً على التعديلات
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
     * حذف السجل
     */
    public function destroy(AttendanceLog $attendanceLog): JsonResponse
    {
        // تم الفحص تلقائياً عبر AttendanceLogPolicy@delete
        $attendanceLog->delete();

        return response()->json(['message' => 'تم حذف سجل الحضور بنجاح.'], 200);
    }
}
