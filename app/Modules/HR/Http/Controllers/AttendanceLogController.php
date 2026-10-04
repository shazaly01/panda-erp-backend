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
        $this->authorizeResource(AttendanceLog::class, 'attendance_log');
    }

    /**
     * عرض سجلات الحضور التفصيلية المفلترة بالكامل
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = Auth::user();

        $query = AttendanceLog::with([
            'employee' => function ($q) {
                $q->withoutGlobalScope('exclude_interns');
            },
            'shift'
        ]);

        if ($request->filled('employment_type') && $request->employment_type !== 'all') {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->withoutGlobalScope('exclude_interns')
                  ->where('employment_type', $request->employment_type);
            });
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        } elseif ($request->filled('date')) {
            $query->where('date', $request->date);
        }

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->whereHas('employee', function ($q) use ($searchTerm) {
                $q->withoutGlobalScope('exclude_interns')
                  ->where(function ($subQ) use ($searchTerm) {
                      $subQ->where('full_name', 'like', '%' . $searchTerm . '%')
                           ->orWhere('employee_number', 'like', '%' . $searchTerm . '%');
                  });
            });
        }

        if ($request->filled('department_id')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->withoutGlobalScope('exclude_interns')->where('department_id', $request->department_id);
            });
        }

        if ($request->filled('pay_group_id')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->withoutGlobalScope('exclude_interns')
                  ->whereHas('contracts', function ($contractQ) use ($request) {
                      $contractQ->where('pay_group_id', $request->pay_group_id)
                                ->where('is_active', true);
                  });
            });
        }

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
        $data = $request->validated();
        $employee = Employee::withInterns()->findOrFail($data['employee_id']);

        try {
            $manualOvertimeMinutes = isset($data['overtime_minutes']) ? (int) $data['overtime_minutes'] : null;

            $log = $this->attendanceService->processDailyAttendance(
                $employee,
                $data['date'],
                $data['check_in'] ?? null,
                $data['check_out'] ?? null,
                $manualOvertimeMinutes
            );

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
        return new AttendanceLogResource($attendanceLog->load(['employee', 'shift']));
    }

    /**
     * تعديل سجل حضور
     */
    public function update(UpdateAttendanceLogRequest $request, AttendanceLog $attendanceLog): JsonResponse
    {
        $data = $request->validated();

        try {
            $checkIn = $data['check_in'] ?? $attendanceLog->check_in;
            $checkOut = $data['check_out'] ?? $attendanceLog->check_out;
            $manualOvertimeMinutes = isset($data['overtime_minutes']) ? (int) $data['overtime_minutes'] : null;

            $updatedLog = $this->attendanceService->processDailyAttendance(
                $attendanceLog->employee,
                $attendanceLog->date->format('Y-m-d'),
                $checkIn,
                $checkOut,
                $manualOvertimeMinutes
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
        $attendanceLog->delete();

        return response()->json(['message' => 'تم حذف سجل الحضور بنجاح.'], 200);
    }

    public function scanBarcode(Request $request): JsonResponse
    {
        $rawEmployeeNumber = $request->input('employee_number');

        // إذا وصل كـ Array (مثل مصفوفة أرقام من لوحة المفاتيح)
        if (is_array($rawEmployeeNumber)) {
            if (array_is_list($rawEmployeeNumber)) {
                // دمج مصفوفة الأرقام المتتالية ['1', '0', '2'] لتصبح "102"
                $rawEmployeeNumber = implode('', $rawEmployeeNumber);
            } else {
                $rawEmployeeNumber = $rawEmployeeNumber['employee_number']
                    ?? $rawEmployeeNumber['barcode']
                    ?? $rawEmployeeNumber['id']
                    ?? $rawEmployeeNumber['value']
                    ?? null;
            }
        }

        if ($rawEmployeeNumber !== null) {
            $request->merge([
                'employee_number' => trim((string) $rawEmployeeNumber),
            ]);
        }

        $request->validate([
            'employee_number' => ['required', 'string'],
            'entry_mode'      => ['nullable', 'string', 'in:hardware,camera,qr,manual'],
        ]);

        $entryMode = $request->input('entry_mode', 'hardware');

        if ($entryMode === 'manual') {
            $this->authorize('manualEntry', AttendanceLog::class);
        }

        $scannedCode = (string) $request->employee_number;
        $now = now();

        $employee = Employee::withInterns()
            ->where(function ($query) use ($scannedCode) {
                $query->where('employee_number', $scannedCode)
                      ->orWhere('barcode', $scannedCode);
            })
            ->first();

        if (!$employee) {
            return response()->json([
                'status' => 'error',
                'message' => 'بطاقة غير صالحة! الموظف غير مسجل بالنظام.'
            ], 404);
        }

        if ($employee->employment_type->value === \App\Modules\HR\Enums\EmploymentType::Intern->value) {
            if ($employee->internship_end_date && $employee->internship_end_date->isPast()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'عذراً، هذا الباركود منتهي الصلاحية لانتهاء فترة التدريب المحددة تلقائياً.'
                ], 403);
            }
        }

        \App\Modules\HR\Models\BiometricPunch::create([
            'employee_id' => $employee->id,
            'punch_time' => $now,
            'punch_type' => $entryMode === 'manual' ? 'manual' : 'auto',
            'device_id' => $entryMode === 'manual' ? 'manual_kiosk' : 'barcode_scanner',
            'is_processed' => true,
        ]);

        try {
            $result = $this->attendanceService->processAutoPunch($employee, $now);

            $employee->load(['profilePhoto', 'user']);

            return response()->json([
                'status' => $result['status'],
                'action' => $result['action'],
                'employee_name' => $employee->full_name,
                'time' => $now->format('h:i A'),
                'message' => $result['message'],
                'profile_photo' => $employee->profilePhoto
                    ? $employee->profilePhoto->url
                    : ($employee->user ? $employee->user->avatar_url : null),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'حدث خطأ أثناء معالجة البصمة: ' . $e->getMessage()
            ], 422);
        }
    }
}