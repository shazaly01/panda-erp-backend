<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\HR\Models\AttendanceLog;
use App\Modules\HR\Services\ManagerAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ManagerAttendanceController extends Controller
{
    private ManagerAttendanceService $managerAttendanceService;

    public function __construct(ManagerAttendanceService $managerAttendanceService)
    {
        $this->managerAttendanceService = $managerAttendanceService;
    }

    /**
     * عرض مصفوفة الحضور اليومية للفريق مع الفلاتر المتقدمة
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('manageTeam', AttendanceLog::class);

        $request->validate([
            'date' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:255'],
            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'status' => ['nullable', 'string', 'in:present,absent,late'],
        ]);

        /** @var User|null $user */
        $user = Auth::user();

        if (!$user || !$user->employee) {
            return response()->json([
                'message' => 'حسابك غير مربوط بملف موظف في النظام.'
            ], 403);
        }

        $filters = [
            'date' => $request->input('date', now()->toDateString()),
            'search' => $request->input('search'),
            'position_id' => $request->input('position_id'),
            'status' => $request->input('status'),
        ];

        $managerId = $user->employee->id;
        $matrix = $this->managerAttendanceService->getTeamDailyMatrix($managerId, $filters);

        return response()->json([
            'data' => $matrix
        ], 200);
    }

    /**
     * اعتماد أو تعديل حضور موظف يدوياً بواسطة المشرف مع الساعات الإضافية
     */
    public function override(Request $request): JsonResponse
    {
        $this->authorize('manageTeam', AttendanceLog::class);

        $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'date' => ['required', 'date'],
            'check_in' => ['nullable', 'date_format:H:i:s,H:i'],
            'check_out' => ['nullable', 'date_format:H:i:s,H:i'],
            'overtime_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        /** @var User|null $user */
        $user = Auth::user();

        if (!$user || !$user->employee) {
            return response()->json([
                'message' => 'حسابك غير مربوط بملف موظف في النظام.'
            ], 403);
        }

        // جلب سجل الحضور الحالي للموظف لذلك اليوم للتحقق من التغييرات الفعلية
        $existingLog = AttendanceLog::where('employee_id', (int) $request->employee_id)
            ->where('date', $request->date)
            ->first();

        // 1. تدقيق صلاحية تعديل وقت الدخول عبر Gate
        $existingCheckIn = $existingLog?->check_in ? substr((string) $existingLog->check_in, 0, 5) : null;
        $newCheckIn = $request->filled('check_in') ? substr((string) $request->input('check_in'), 0, 5) : null;
        $isCheckInChanged = ($existingCheckIn !== $newCheckIn);

        if ($isCheckInChanged && Gate::denies('overrideTeamCheckIn', AttendanceLog::class)) {
            return response()->json([
                'message' => 'غير مصرح لك بتسجيل أو تعديل وقت الدخول للموظف.'
            ], 403);
        }

        // 2. تدقيق صلاحية تعديل وقت الخروج عبر Gate
        $existingCheckOut = $existingLog?->check_out ? substr((string) $existingLog->check_out, 0, 5) : null;
        $newCheckOut = $request->filled('check_out') ? substr((string) $request->input('check_out'), 0, 5) : null;
        $isCheckOutChanged = ($existingCheckOut !== $newCheckOut);

        if ($isCheckOutChanged && Gate::denies('overrideTeamCheckOut', AttendanceLog::class)) {
            return response()->json([
                'message' => 'غير مصرح لك بتسجيل أو تعديل وقت الخروج للموظف.'
            ], 403);
        }

        // في حال عدم امتلاك المشرف لصلاحية حقل معين، يتم تثبيت القيمة السابقة كما هي لضمان عدم التلاعب
        $checkInToSave = Gate::allows('overrideTeamCheckIn', AttendanceLog::class)
            ? $request->check_in
            : ($existingLog?->check_in ?? null);

        $checkOutToSave = Gate::allows('overrideTeamCheckOut', AttendanceLog::class)
            ? $request->check_out
            : ($existingLog?->check_out ?? null);

        try {
            $manualOvertimeMinutes = $request->filled('overtime_minutes')
                ? (int) $request->input('overtime_minutes')
                : null;

            $log = $this->managerAttendanceService->overrideTeamAttendance(
                $user->employee->id,
                (int) $request->employee_id,
                $request->date,
                $checkInToSave,
                $checkOutToSave,
                $request->reason,
                $manualOvertimeMinutes
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