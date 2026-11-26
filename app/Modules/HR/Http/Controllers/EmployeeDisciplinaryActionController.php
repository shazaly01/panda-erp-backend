<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\HR\Http\Requests\Disciplinary\CancelDisciplinaryActionRequest;
use App\Modules\HR\Http\Requests\Disciplinary\StoreDisciplinaryActionRequest;
use App\Modules\HR\Http\Resources\EmployeeDisciplinaryActionResource;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\EmployeeDisciplinaryAction;
use App\Modules\HR\Models\ViolationType;
use App\Modules\HR\Services\DisciplinaryEngineService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class EmployeeDisciplinaryActionController extends Controller
{
    public function __construct(
        private readonly DisciplinaryEngineService $disciplinaryEngine
    ) {
        $this->authorizeResource(EmployeeDisciplinaryAction::class, 'disciplinary_action');
    }

    /**
     * عرض سجل المخالفات والجزاءات التأديبية مع التصفية المتقدمة
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User|null $user */
        $user = Auth::user();

        $query = EmployeeDisciplinaryAction::query()
            ->with([
                'employee',
                'violationType',
                'escalationRule',
                'payrollInput',
                'approver',
                'creator',
            ]);

        // عزل الخدمة الذاتية للموظف العادي لعرض جزاءاته الشخصية فقط
        if ($user && !$user->hasRole('HR Manager') && !$user->can('hr.disciplinary_actions.view') && $user->employee_id) {
            $query->where('employee_id', $user->employee_id);
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('violation_type_id')) {
            $query->where('violation_type_id', $request->violation_type_id);
        }

        if ($request->filled('action_type')) {
            $query->where('action_type', $request->action_type);
        }

        if ($request->filled('status')) {
            $today = now()->toDateString();

            if ($request->status === 'expired') {
                $query->where('status', EmployeeDisciplinaryAction::STATUS_APPROVED)
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<', $today);
            } elseif ($request->status === EmployeeDisciplinaryAction::STATUS_APPROVED) {
                $query->where('status', EmployeeDisciplinaryAction::STATUS_APPROVED)
                    ->where(function ($q) use ($today): void {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>=', $today);
                    });
            } else {
                $query->where('status', $request->status);
            }
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('violation_date', [$request->start_date, $request->end_date]);
        } elseif ($request->filled('violation_date')) {
            $query->where('violation_date', $request->violation_date);
        }

        if ($request->filled('department_id')) {
            $query->whereHas('employee', function ($q) use ($request): void {
                $q->where('department_id', $request->department_id);
            });
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search): void {
                $q->where('number', 'like', "%{$search}%")
                  ->orWhereHas('employee', function ($empQ) use ($search): void {
                      $empQ->where('full_name', 'like', "%{$search}%")
                           ->orWhere('employee_number', 'like', "%{$search}%");
                  });
            });
        }

        $perPage = min(max($request->integer('per_page', 20), 5), 100);

        $actions = $query->orderByDesc('violation_date')
            ->orderByDesc('id')
            ->paginate($perPage);

        return EmployeeDisciplinaryActionResource::collection($actions);
    }

    /**
     * معاينة الدرجة العقابية المتوقعة والأثر المالي وتاريخ التقادم قبل تسجيل المخالفة
     */
    public function previewEscalation(Request $request): JsonResponse
    {
        $this->authorize('create', EmployeeDisciplinaryAction::class);

        $validated = $request->validate([
            'employee_id'       => ['required', 'integer', 'exists:employees,id'],
            'violation_type_id' => ['required', 'integer', 'exists:hr_violation_types,id'],
            'violation_date'    => ['required', 'date'],
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);
        $violationType = ViolationType::with('escalationRules')->findOrFail($validated['violation_type_id']);
        $dateCarbon = Carbon::parse($validated['violation_date'])->startOfDay();

        $rule = $this->disciplinaryEngine->resolveNextEscalationRule($employee, $violationType, $dateCarbon);

        if (!$rule) {
            return response()->json([
                'message' => 'اللائحة المحددة لا تحتوي على درجات تدرج عقابي معرفة.',
            ], 422);
        }

        $financials = $this->disciplinaryEngine->calculateFinancialDeduction($employee, $rule, $dateCarbon);
        $validityMonths = max(1, (int) $rule->validity_months);
        $expiresAt = $dateCarbon->copy()->addMonths($validityMonths)->toDateString();

        return response()->json([
            'data' => [
                'step_order'        => (int) $rule->step_order,
                'action_type'       => $rule->action_type,
                'action_type_label' => $rule->action_type_label,
                'deduction_type'    => $rule->deduction_type,
                'deduction_days'    => $financials['deduction_days'],
                'deduction_amount'  => $financials['deduction_amount'],
                'suspension_days'   => (int) $rule->suspension_days,
                'validity_months'   => $validityMonths,
                'expires_at'        => $expiresAt,
                'requires_approval' => (bool) $violationType->requires_approval,
                'notes'             => $rule->notes,
            ],
        ]);
    }

    /**
     * تسجيل مخالفة جديدة على موظف وتطبيق سلم التدرج العقابي آلياً
     */
    public function store(StoreDisciplinaryActionRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $employee = Employee::findOrFail($data['employee_id']);
            $violationType = ViolationType::findOrFail($data['violation_type_id']);

            $autoApprove = null;
            if (array_key_exists('auto_approve', $data) && $data['auto_approve']) {
                $this->authorize('approve', EmployeeDisciplinaryAction::class);
                $autoApprove = true;
            }

            $action = $this->disciplinaryEngine->recordViolation(
                employee: $employee,
                violationType: $violationType,
                violationDate: $data['violation_date'],
                reason: $data['reason'] ?? null,
                attendanceLogId: null,
                payPeriodId: null,
                forceAutoApprove: $autoApprove,
                actorUserId: Auth::id()
            );

            return response()->json([
                'message' => 'تم تسجيل المخالفة وتحديد الجزاء اللائحي بنجاح.',
                'data'    => new EmployeeDisciplinaryActionResource($action),
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * عرض تفاصيل جزاء تأديبي محدد
     */
    public function show(EmployeeDisciplinaryAction $disciplinaryAction): EmployeeDisciplinaryActionResource
    {
        $disciplinaryAction->load([
            'employee',
            'violationType',
            'escalationRule',
            'attendanceLog',
            'payPeriod',
            'payrollInput',
            'approver',
            'canceller',
            'creator',
        ]);

        return new EmployeeDisciplinaryActionResource($disciplinaryAction);
    }

    /**
     * اعتماد الجزاء التأديبي وتفعيل أثره المالي في مدخلات الرواتب
     */
    public function approve(EmployeeDisciplinaryAction $disciplinaryAction): JsonResponse
    {
        $this->authorize('approve', $disciplinaryAction);

        try {
            $approved = $this->disciplinaryEngine->approveAction($disciplinaryAction, Auth::id());

            return response()->json([
                'message' => 'تم اعتماد الجزاء التأديبي وتنفيذ أثره بنجاح.',
                'data'    => new EmployeeDisciplinaryActionResource($approved),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * إلغاء الجزاء التأديبي والتراجع عن الاستقطاع المالي غير المرحّل
     */
    public function cancel(
        CancelDisciplinaryActionRequest $request,
        EmployeeDisciplinaryAction $disciplinaryAction
    ): JsonResponse {
        $this->authorize('cancel', $disciplinaryAction);

        try {
            $cancelled = $this->disciplinaryEngine->cancelAction(
                $disciplinaryAction,
                $request->validated('cancellation_reason'),
                Auth::id()
            );

            return response()->json([
                'message' => 'تم إلغاء الجزاء التأديبي والتراجع عن أثره بنجاح.',
                'data'    => new EmployeeDisciplinaryActionResource($cancelled),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * حذف سجل مخالفة (مسموح فقط للسجلات المعلقة أو الملغاة غير المرتبطة بمسير رواتب)
     */
    public function destroy(EmployeeDisciplinaryAction $disciplinaryAction): JsonResponse
    {
        if ($disciplinaryAction->status === EmployeeDisciplinaryAction::STATUS_APPROVED) {
            return response()->json([
                'message' => 'لا يمكن حذف جزاء معتمد مباشرة؛ يجب إلغاؤه أولاً لضمان سلامة السجل التدقيقي.',
            ], 422);
        }

        if ($disciplinaryAction->payrollInput()->where('is_processed', true)->exists()) {
            return response()->json([
                'message' => 'لا يمكن حذف هذا السجل لارتباطه باستقطاع مالي مرحّل في مسير رواتب.',
            ], 422);
        }

        $disciplinaryAction->payrollInput()->delete();
        $disciplinaryAction->delete();

        return response()->json([
            'message' => 'تم حذف سجل المخالفة بنجاح.',
        ]);
    }
}