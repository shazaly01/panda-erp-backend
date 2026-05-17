<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\AttendanceLog;
use App\Modules\HR\Services\ManagerAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ManagerAttendanceController extends Controller
{
    private ManagerAttendanceService $managerAttendanceService;

    public function __construct(ManagerAttendanceService $managerAttendanceService)
    {
        $this->managerAttendanceService = $managerAttendanceService;
    }

    /**
     * عرض مصفوفة الحضور اليومية للفريق (من بصم ومن غاب)
     */
    public function index(Request $request): JsonResponse
    {
        // 1. التحقق من الصلاحية عبر Policy كما في القاعدة المعمارية
        $this->authorize('manageTeam', AttendanceLog::class);

        // 2. التحقق من صحة المدخلات
        $request->validate([
            'date' => ['nullable', 'date']
        ]);

        $user = Auth::user();

        // 3. التأكد من أن المستخدم الحالي لديه ملف موظف (لكي يكون مشرفاً)
        if (!$user->employee) {
            return response()->json([
                'message' => 'حسابك غير مربوط بملف موظف في النظام.'
            ], 403);
        }

        // 4. إذا لم يرسل تاريخ، نعرض تاريخ اليوم افتراضياً
        $date = $request->input('date', now()->toDateString());
        $managerId = $user->employee->id;

        // 5. استدعاء الخدمة لجلب المصفوفة
        $matrix = $this->managerAttendanceService->getTeamDailyMatrix($managerId, $date);

        return response()->json([
            'data' => $matrix
        ], 200);
    }

    /**
     * اعتماد أو تعديل حضور موظف يدوياً بواسطة المشرف
     */
    public function override(Request $request): JsonResponse
    {
        // 1. التحقق من الصلاحية عبر Policy
        $this->authorize('manageTeam', AttendanceLog::class);

        // 2. التحقق من صحة المدخلات
        $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'date' => ['required', 'date'],
            // يقبل صيغة الساعات والدقائق (الثواني اختيارية)
            'check_in' => ['nullable', 'date_format:H:i:s,H:i'],
            'check_out' => ['nullable', 'date_format:H:i:s,H:i'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $user = Auth::user();

        if (!$user->employee) {
            return response()->json([
                'message' => 'حسابك غير مربوط بملف موظف في النظام.'
            ], 403);
        }

        try {
            // 3. تفويض العمليات المعقدة والتدقيق للخدمة (Service)
            $log = $this->managerAttendanceService->overrideTeamAttendance(
                $user->employee->id,
                (int) $request->employee_id,
                $request->date,
                $request->check_in,
                $request->check_out,
                $request->reason
            );

            return response()->json([
                'message' => 'تم اعتماد التعديل اليدوي وتسجيل التدقيق بنجاح.',
                'data' => $log
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 422);
        }
    }
}
