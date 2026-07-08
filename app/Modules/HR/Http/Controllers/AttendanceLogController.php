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
     * عرض سجلات الحضور التفصيلية المفلترة بالكامل
     * يدعم حصر وفرز (الموظفين، المتدربين، أو كلاهما معاً) لحساب الأعداد الفعلية
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        // تم الفحص تلقائياً عبر AttendanceLogPolicy@viewAny

        $user = Auth::user();

        // 🌟 إصلاح جوهري: كسر العزل داخل الـ Eager Loading لضمان شحن بيانات المتدربين والموظفين معاً في الاستعلام الرئيسي
        $query = AttendanceLog::with([
            'employee' => function ($q) {
                $q->withoutGlobalScope('exclude_interns');
            },
            'shift'
        ]);

        // 🌟 1. فلترة نوع العمل (employment_type) لحصر الحضور ومعرفة العدد الفعلي للفئة
        // الخيارات المتوقعة من الواجهة الأمامية: 'full_time' (موظفين)، 'intern' (متدربين)، أو 'all' (الكل)
        if ($request->filled('employment_type') && $request->employment_type !== 'all') {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->withoutGlobalScope('exclude_interns')
                  ->where('employment_type', $request->employment_type);
            });
        }

        // 2. فلترة البحث المباشر بمعرف الموظف (إن وجد)
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        // 3. فلترة النطاق الزمني (بين تاريخين) لحل مشكلة عدم عمل فلاتر الجدول التفصيلي
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        } elseif ($request->filled('date')) {
            // توافقية رجعية في حال أرسل جزء آخر من النظام حقل تاريخ منفرد
            $query->where('date', $request->date);
        }

        // 4. فلترة البحث الذكي بنص (اسم الموظف الكامل أو الرقم الوظيفي) ليشمل الجميع بالسجلات
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

        // 5. فلترة القسم الإداري الخاص بالموظف أو المتدرب
        if ($request->filled('department_id')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->withoutGlobalScope('exclude_interns')->where('department_id', $request->department_id);
            });
        }

        // منطق الخدمة الذاتية (ESS): إذا لم يكن مديراً، يرى سجلاته فقط
        if (!$user->can('hr.attendance.manage') && $user->employee_id) {
            $query->where('employee_id', $user->employee_id);
        }

        // يعيد البيانات مجمعة ومقسمة لصفحات، حيث يحتوي كائن الـ meta تلقائياً على الـ total الفعلي بعد الفلترة
        return AttendanceLogResource::collection($query->orderByDesc('date')->paginate(30));
    }

    /**
     * إدخال سجل حضور يدوي (يدعم الموظفين والمتدربين)
     */
    public function store(StoreAttendanceLogRequest $request): JsonResponse
    {
        // تم الفحص تلقائياً عبر AttendanceLogPolicy@create

        $data = $request->validated();

        // استخدام withInterns لضمان قبول تسجيل حضور يدوي للمتدربين من شاشتهم الإدارية
        $employee = Employee::withInterns()->findOrFail($data['employee_id']);

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

   /**
     * تسجيل الدخول السريع عبر الباركود والـ QR Code (Kiosk Mode)
     * مع الفحص التلقائي لصلاحية باركود المتدربين بناءً على تاريخ نهاية التدريب
     */
    public function scanBarcode(Request $request): JsonResponse
    {
        $request->validate([
            'employee_number' => 'required|string'
        ]);

        $scannedCode = $request->employee_number;
        $now = now();

        // 1. البحث عن الموظف أو المتدرب عبر كسر العزل الآمن للـ Global Scope لحل ثغرة الـ 404
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

        // 2. 🛡️ التحقق تلقائياً من صلاحية باركود المتدرب بناءً على تاريخ انتهاء التدريب
        if ($employee->employment_type->value === \App\Modules\HR\Enums\EmploymentType::Intern->value) {
            if ($employee->internship_end_date && $employee->internship_end_date->isPast()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'عذراً، هذا الباركود منتهي الصلاحية لانتهاء فترة التدريب المحددة تلقائياً.'
                ], 403);
            }
        }

        // 3. تسجيل الضربة الخام في جدول البصمات البيومترية بعد اجتياز فحص الصلاحية
        \App\Modules\HR\Models\BiometricPunch::create([
            'employee_id' => $employee->id,
            'punch_time' => $now,
            'punch_type' => 'auto',
            'device_id' => 'barcode_scanner',
            'is_processed' => true,
        ]);

        // 4. تفويض معالجة البيانات بالكامل لمحرك الحضور (الخدمة)
        try {
            $result = $this->attendanceService->processAutoPunch($employee, $now);

            // شحن علاقات الصور والمستخدم لتجنب الـ Lazy Loading وتحسين الأداء
            $employee->load(['profilePhoto', 'user']);

            return response()->json([
                'status' => $result['status'],
                'action' => $result['action'],
                'employee_name' => $employee->full_name,
                'time' => $now->format('h:i A'),
                'message' => $result['message'],
                // جلب رابط الصورة الشخصية المرفوعة، وإذا لم توجد يجلب الـ Avatar الخاص بحسابه
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
