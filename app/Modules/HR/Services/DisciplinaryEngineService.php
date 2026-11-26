<?php

declare(strict_types=1);

namespace App\Modules\HR\Services;

use App\Modules\Core\Services\SequenceService;
use App\Modules\HR\Enums\EmployeeStatus;
use App\Modules\HR\Models\AttendanceLog;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\EmployeeDisciplinaryAction;
use App\Modules\HR\Models\PayPeriod;
use App\Modules\HR\Models\PayrollInput;
use App\Modules\HR\Models\ViolationEscalationRule;
use App\Modules\HR\Models\ViolationType;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class DisciplinaryEngineService
{
    public function __construct(
        protected SequenceService $sequenceService
    ) {}

    /**
     * تسجيل مخالفة جديدة لموظف (يدوياً أو آلياً) وتحديد درجة العقوبة المستحقة حسب السلم والتقادم
     */
    public function recordViolation(
        Employee $employee,
        ViolationType $violationType,
        Carbon|string $violationDate,
        ?string $reason = null,
        ?int $attendanceLogId = null,
        ?int $payPeriodId = null,
        ?bool $forceAutoApprove = null,
        ?int $actorUserId = null
    ): EmployeeDisciplinaryAction {
        return DB::transaction(function () use (
            $employee,
            $violationType,
            $violationDate,
            $reason,
            $attendanceLogId,
            $payPeriodId,
            $forceAutoApprove,
            $actorUserId
        ): EmployeeDisciplinaryAction {
            if (!$violationType->is_active) {
                throw new Exception("اللائحة المحددة ({$violationType->name}) موقوفة حالياً ولا يمكن تسجيل مخالفات عليها.");
            }

            $dateCarbon = $violationDate instanceof Carbon
                ? $violationDate->copy()->startOfDay()
                : Carbon::parse($violationDate)->startOfDay();

            $dateString = $dateCarbon->toDateString();

            // 1. تحديد الدرجة العقابية التالية بناءً على سجل الموظف الساري (المعلق أو المعتمد غير المنتهي بالتقادم)
            $rule = $this->resolveNextEscalationRule($employee, $violationType, $dateCarbon);

            if (!$rule) {
                throw new Exception("اللائحة ({$violationType->name}) لا تحتوي على درجات تدرج عقابي معرفة.");
            }

            // 2. احتساب الأثر المالي بدقة من عقد الموظف الساري في تاريخ المخالفة
            $financials = $this->calculateFinancialDeduction($employee, $rule, $dateCarbon);

            // 3. احتساب تاريخ سقوط الجزاء بالتقادم (validity_months)
            $validityMonths = max(1, (int) $rule->validity_months);
            $expiresAt = $dateCarbon->copy()->addMonths($validityMonths)->toDateString();

            // 4. تحديد حالة الاعتماد المبدئية (مع حاجز أمان إلزامي لإنهاء الخدمة)
            $isTermination = ($rule->action_type === ViolationEscalationRule::ACTION_TERMINATION);
            $shouldAutoApprove = $forceAutoApprove ?? !$violationType->requires_approval;

            if ($isTermination) {
                // لا يُفصل موظف تلقائياً أبداً؛ يجب مرور توصية إنهاء الخدمة باعتماد صريح
                $shouldAutoApprove = false;
            }

            $creatorId = $actorUserId ?? Auth::id();

            // 5. إنشاء سجل المخالفة والجزاء بلقطة ثابتة (Immutable Snapshot)
            $action = EmployeeDisciplinaryAction::create([
                'number'             => $this->generateActionNumber(),
                'employee_id'        => $employee->id,
                'violation_type_id'  => $violationType->id,
                'escalation_rule_id' => $rule->id,
                'attendance_log_id'  => $attendanceLogId,
                'pay_period_id'      => $payPeriodId,
                'step_order'         => $rule->step_order,
                'action_type'        => $rule->action_type,
                'deduction_days'     => $financials['deduction_days'],
                'deduction_amount'   => $financials['deduction_amount'],
                'suspension_days'    => $rule->action_type === ViolationEscalationRule::ACTION_SUSPENSION
                    ? (int) $rule->suspension_days
                    : 0,
                'violation_date'     => $dateString,
                'expires_at'         => $expiresAt,
                'status'             => EmployeeDisciplinaryAction::STATUS_PENDING,
                'reason'             => $reason ?: $rule->notes,
                'created_by'         => $creatorId,
            ]);

            // 6. تنفيذ الاعتماد والأثر المالي فوراً إذا لم تكن اللائحة تتطلب اعتماداً مسبقاً
            if ($shouldAutoApprove) {
                return $this->approveAction($action, $creatorId);
            }

            return $action->fresh(['employee', 'violationType', 'escalationRule']);
        });
    }

    /**
     * تحديد الدرجة العقابية التالية في سلم اللائحة مع مراعاة التراكم وسقوط الجزاءات السابقة بالتقادم
     */
    public function resolveNextEscalationRule(
        Employee $employee,
        ViolationType $violationType,
        Carbon $violationDate
    ): ?ViolationEscalationRule {
        $steps = $violationType->escalationRules()->orderBy('step_order')->get();

        if ($steps->isEmpty()) {
            return null;
        }

        // جلب آخر جزاء مؤثر في التدرج (معلق أو معتمد لم ينتهِ تاريخ صلاحيته expires_at قبل تاريخ المخالفة الجديدة)
        $lastEscalatableAction = EmployeeDisciplinaryAction::query()
            ->where('employee_id', $employee->id)
            ->where('violation_type_id', $violationType->id)
            ->escalatableOnDate($violationDate)
            ->orderByDesc('step_order')
            ->orderByDesc('violation_date')
            ->orderByDesc('id')
            ->first();

        // إذا لم يوجد جزاء سارٍ (أول مخالفة أو انتهت مدة التقادم للجزاء السابق) نبدأ من الدرجة الأولى
        if (!$lastEscalatableAction) {
            return $steps->first();
        }

        // الانتقال للدرجة التالية في السلم، أو البقاء عند أعلى درجة إذا بلغ الموظف سقف اللائحة
        $nextStep = $steps->first(
            fn (ViolationEscalationRule $rule): bool => (int) $rule->step_order > (int) $lastEscalatableAction->step_order
        );

        return $nextStep ?? $steps->last();
    }

    /**
     * احتساب قيمة الخصم المالي بناءً على الراتب الأساسي في عقد الموظف الساري وسياسة أيام الشهر
     */
    public function calculateFinancialDeduction(
        Employee $employee,
        ViolationEscalationRule $rule,
        Carbon $violationDate
    ): array {
        if (!$rule->hasFinancialDeduction()) {
            return [
                'deduction_days'   => 0.0,
                'deduction_amount' => 0.0,
            ];
        }

        $contract = $employee->contractForDate($violationDate)
            ?? $employee->activeContract
            ?? $employee->currentContract;

        $basicSalary = (float) ($contract?->basic_salary ?? 0.0);
        $workingDays = (int) ($contract?->overtimePolicy?->working_days_per_month ?? 30);

        if ($workingDays <= 0) {
            $workingDays = 30;
        }

        $dailyWage = $workingDays > 0 ? ($basicSalary / $workingDays) : 0.0;
        $deductionValue = (float) $rule->deduction_value;

        return match ($rule->deduction_type) {
            ViolationEscalationRule::DEDUCTION_DAYS => [
                'deduction_days'   => round($deductionValue, 2),
                'deduction_amount' => round($dailyWage * $deductionValue, 2),
            ],
            ViolationEscalationRule::DEDUCTION_FIXED => [
                'deduction_days'   => 0.0,
                'deduction_amount' => round($deductionValue, 2),
            ],
            ViolationEscalationRule::DEDUCTION_PERCENTAGE => [
                'deduction_days'   => 0.0,
                'deduction_amount' => round($basicSalary * ($deductionValue / 100.0), 2),
            ],
            default => [
                'deduction_days'   => 0.0,
                'deduction_amount' => 0.0,
            ],
        };
    }

    /**
     * اعتماد الجزاء التأديبي وتفعيل أثره المالي والتشغيلي في الرواتب والحضور
     */
    public function approveAction(EmployeeDisciplinaryAction $action, ?int $approverId = null): EmployeeDisciplinaryAction
    {
        return DB::transaction(function () use ($action, $approverId): EmployeeDisciplinaryAction {
            $lockedAction = EmployeeDisciplinaryAction::where('id', $action->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAction->status === EmployeeDisciplinaryAction::STATUS_APPROVED) {
                return $lockedAction->load(['employee', 'violationType', 'escalationRule', 'payrollInput']);
            }

            if ($lockedAction->status === EmployeeDisciplinaryAction::STATUS_CANCELLED) {
                throw new Exception("لا يمكن اعتماد جزاء ملغى مسبقاً.");
            }

            $resolvedApproverId = $approverId ?? Auth::id();
            $violationType = $lockedAction->violationType;
            $rule = $lockedAction->escalationRule;

            // إعادة ضبط تاريخ التقادم لبدء السريان الفعلي
            $validityMonths = max(1, (int) ($rule?->validity_months ?? 3));
            $expiresAt = $lockedAction->violation_date
                ? $lockedAction->violation_date->copy()->addMonths($validityMonths)->toDateString()
                : now()->addMonths($validityMonths)->toDateString();

            $lockedAction->update([
                'status'      => EmployeeDisciplinaryAction::STATUS_APPROVED,
                'approved_by' => $resolvedApproverId,
                'approved_at' => now(),
                'expires_at'  => $expiresAt,
            ]);

            // 1. إذا ترتب على الجزاء خصم مالي: إنشاء قيد استقطاع رسمي مربوط في hr_payroll_inputs
            $deductionAmount = round((float) $lockedAction->deduction_amount, 2);
            if ($deductionAmount > 0) {
                $reasonText = sprintf(
                    'جزاء تأديبي (%s): %s - الدرجة %d (%s)',
                    $lockedAction->number,
                    $violationType?->name ?? 'مخالفة لائحية',
                    $lockedAction->step_order,
                    $lockedAction->action_type_label
                );

                PayrollInput::updateOrCreate(
                    ['disciplinary_action_id' => $lockedAction->id],
                    [
                        'employee_id'  => $lockedAction->employee_id,
                        'type'         => 'penalty',
                        'amount'       => $deductionAmount,
                        'date'         => $lockedAction->violation_date->toDateString(),
                        'reason'       => mb_substr($reasonText, 0, 255),
                        'is_processed' => false,
                        'created_by'   => $resolvedApproverId ?? $lockedAction->created_by,
                    ]
                );
            }

            // 2. إذا كان الإجراء إيقافاً مؤقتاً: تسجيل أيام الإيقاف في سجل الحضور لمنع احتسابها غياباً مكرراً
            if ($lockedAction->action_type === ViolationEscalationRule::ACTION_SUSPENSION && (int) $lockedAction->suspension_days > 0) {
                $this->applySuspensionToAttendance($lockedAction);
            }

            // 3. إذا كان الإجراء المعتمد هو إنهاء الخدمة: تحديث حالة الموظف وإغلاق عقده النشط
            if ($lockedAction->action_type === ViolationEscalationRule::ACTION_TERMINATION) {
                $employee = Employee::withInterns()->find($lockedAction->employee_id);
                if ($employee) {
                    $employee->update([
                        'status' => EmployeeStatus::Dismissed->value,
                    ]);

                    $activeContract = $employee->contractForDate($lockedAction->violation_date)
                        ?? $employee->activeContract;

                    if ($activeContract) {
                        $activeContract->update([
                            'end_date'  => $lockedAction->violation_date->toDateString(),
                            'is_active' => false,
                        ]);
                    }
                }
            }

            return $lockedAction->fresh(['employee', 'violationType', 'escalationRule', 'payrollInput', 'approver']);
        });
    }

    /**
     * إلغاء الجزاء التأديبي والتراجع عن الاستقطاع المالي غير المرحّل وسجلات الإيقاف
     */
    public function cancelAction(
        EmployeeDisciplinaryAction $action,
        string $cancellationReason,
        ?int $cancellerId = null
    ): EmployeeDisciplinaryAction {
        return DB::transaction(function () use ($action, $cancellationReason, $cancellerId): EmployeeDisciplinaryAction {
            $lockedAction = EmployeeDisciplinaryAction::where('id', $action->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAction->status === EmployeeDisciplinaryAction::STATUS_CANCELLED) {
                throw new Exception("هذا الجزاء ملغى بالفعل.");
            }

            // التحقق من حالة الاستقطاع المالي المرتبط في الرواتب
            $payrollInput = PayrollInput::where('disciplinary_action_id', $lockedAction->id)->first();
            if ($payrollInput) {
                if ($payrollInput->is_processed) {
                    throw new Exception(
                        "لا يمكن إلغاء الجزاء ({$lockedAction->number}) لأن الخصم المالي المرتبط به قد تم ترحيله بالفعل في مسير رواتب معتمد. يجب التراجع عن مسير الرواتب أولاً."
                    );
                }

                $payrollInput->delete();
            }

            // إزالة سجلات الإيقاف المؤقت من الحضور إذا كان الجزاء إيقافاً مؤقتاً
            if ($lockedAction->action_type === ViolationEscalationRule::ACTION_SUSPENSION) {
                AttendanceLog::where('employee_id', $lockedAction->employee_id)
                    ->where('status', 'suspended')
                    ->where('override_reason', 'like', "%{$lockedAction->number}%")
                    ->delete();
            }

            // إذا كان الجزاء المعتمد سابقاً هو إنهاء خدمة، إعادة حالة الموظف إلى في الخدمة
            if (
                $lockedAction->status === EmployeeDisciplinaryAction::STATUS_APPROVED
                && $lockedAction->action_type === ViolationEscalationRule::ACTION_TERMINATION
            ) {
                $employee = Employee::withInterns()->find($lockedAction->employee_id);
                if ($employee && ($employee->status?->value ?? $employee->status) === EmployeeStatus::Dismissed->value) {
                    $employee->update([
                        'status' => EmployeeStatus::InService->value,
                    ]);
                }
            }

            $lockedAction->update([
                'status'              => EmployeeDisciplinaryAction::STATUS_CANCELLED,
                'cancelled_by'        => $cancellerId ?? Auth::id(),
                'cancelled_at'        => now(),
                'cancellation_reason' => mb_substr(trim($cancellationReason), 0, 255),
            ]);

            return $lockedAction->fresh(['employee', 'violationType', 'escalationRule', 'canceller']);
        });
    }

    /**
     * تسجيل أيام الإيقاف المؤقت في سجل الحضور بحالة suspended حتى لا تُحسب غياباً بدون إذن
     */
    protected function applySuspensionToAttendance(EmployeeDisciplinaryAction $action): void
    {
        $days = (int) $action->suspension_days;
        if ($days <= 0) {
            return;
        }

        $startDate = Carbon::today();
        $reason = "إيقاف مؤقت عن العمل بموجب القرار التأديبي رقم ({$action->number})";

        for ($i = 0; $i < $days; $i++) {
            $targetDate = $startDate->copy()->addDays($i)->toDateString();

            $existingLog = AttendanceLog::where('employee_id', $action->employee_id)
                ->where('date', $targetDate)
                ->first();

            // لا نغطي على يوم سجل فيه الموظف حضوراً فعلياً مسبقاً
            if ($existingLog && $existingLog->check_in !== null) {
                continue;
            }

            AttendanceLog::updateOrCreate(
                [
                    'employee_id' => $action->employee_id,
                    'date'        => $targetDate,
                ],
                [
                    'shift_id'            => $existingLog?->shift_id,
                    'check_in'            => null,
                    'check_out'           => null,
                    'delay_minutes'       => 0,
                    'early_leave_minutes' => 0,
                    'overtime_minutes'    => 0,
                    'status'              => 'suspended',
                    'is_manual_override'  => true,
                    'override_reason'     => $reason,
                ]
            );
        }
    }

    /**
     * التقييم الآلي لسجل الحضور والانصراف اليومي (يُستدعى من AttendanceService)
     * يعالج: التأخير الصباحي، الانصراف المبكر (لعقود الدخول والخروج)، الغياب بدون إذن، والتراكم الشهري.
     */
    public function evaluateAttendanceLog(AttendanceLog $log): void
    {
        $employee = Employee::withInterns()->find($log->employee_id);
        if (!$employee) {
            return;
        }

        // جلب جميع اللوائح الآلية النشطة
        $automatedRules = ViolationType::active()
            ->whereIn('detection_mode', [
                ViolationType::DETECTION_ATTENDANCE_LATE,
                ViolationType::DETECTION_ATTENDANCE_EARLY_LEAVE,
                ViolationType::DETECTION_ATTENDANCE_ABSENT,
            ])
            ->get();

        if ($automatedRules->isEmpty()) {
            return;
        }

        $logDate = $log->date instanceof Carbon
            ? $log->date->copy()->startOfDay()
            : Carbon::parse($log->date)->startOfDay();

        foreach ($automatedRules as $violationType) {
            if ($violationType->trigger_mode === ViolationType::TRIGGER_PER_INCIDENT) {
                $this->evaluatePerIncidentLog($employee, $log, $violationType, $logDate);
            } elseif ($violationType->trigger_mode === ViolationType::TRIGGER_CUMULATIVE_MONTHLY) {
                $this->evaluateCumulativePeriodLog($employee, $log, $violationType, $logDate);
            }
        }
    }

    /**
     * تقييم المخالفة الآلية بنظام الحادثة الواحدة (Per-Incident) مع التنظيف التلقائي عند تصحيح البصمة
     */
    protected function evaluatePerIncidentLog(
        Employee $employee,
        AttendanceLog $log,
        ViolationType $violationType,
        Carbon $logDate
    ): void {
        $isApplicable = $this->isDetectionModeApplicableForContract(
            $employee,
            $violationType->detection_mode,
            $logDate
        );

        $metricValue = $isApplicable
            ? $this->extractMetricFromLog($log, $violationType->detection_mode)
            : 0;
        $isViolated = $isApplicable && $this->matchesThreshold($metricValue, $violationType);

        $existingAction = EmployeeDisciplinaryAction::where('employee_id', $employee->id)
            ->where('violation_type_id', $violationType->id)
            ->where('attendance_log_id', $log->id)
            ->whereIn('status', [
                EmployeeDisciplinaryAction::STATUS_PENDING,
                EmployeeDisciplinaryAction::STATUS_APPROVED,
            ])
            ->first();

        // إذا تم تعديل البصمة وتبرير التأخير/الغياب ولم يعد يستوفي شرط المخالفة: إلغاء الجزاء غير المرحّل تلقائياً
        if (!$isViolated) {
            if ($existingAction) {
                $linkedInput = PayrollInput::where('disciplinary_action_id', $existingAction->id)->first();
                if (!$linkedInput || !$linkedInput->is_processed) {
                    $this->cancelAction(
                        $existingAction,
                        'إلغاء تلقائي بعد تحديث سجل الحضور وزوال سبب المخالفة.',
                        Auth::id()
                    );
                }
            }
            return;
        }

        // منع إصدار جزاء مكرر لنفس سجل الحضور ونفس اللائحة
        if ($existingAction) {
            return;
        }

        $autoReason = $this->buildAutomatedReason($violationType, $logDate, $metricValue, false);

        $this->recordViolation(
            employee: $employee,
            violationType: $violationType,
            violationDate: $logDate,
            reason: $autoReason,
            attendanceLogId: $log->id,
            payPeriodId: null
        );
    }

    /**
     * تقييم المخالفة الآلية بنظام التراكم خلال الفترة المالية (Cumulative Monthly)
     */
    protected function evaluateCumulativePeriodLog(
        Employee $employee,
        AttendanceLog $log,
        ViolationType $violationType,
        Carbon $logDate
    ): void {
        if (!$this->isDetectionModeApplicableForContract($employee, $violationType->detection_mode, $logDate)) {
            return;
        }

        $contract = $employee->contractForDate($logDate)
            ?? $employee->activeContract
            ?? $employee->currentContract;

        $period = PayPeriod::query()
            ->when($contract?->pay_group_id, fn ($q) => $q->where('pay_group_id', $contract->pay_group_id))
            ->where('start_date', '<=', $logDate->toDateString())
            ->where('end_date', '>=', $logDate->toDateString())
            ->first();

        if (!$period) {
            return;
        }

        $periodLogs = AttendanceLog::where('employee_id', $employee->id)
            ->whereBetween('date', [
                $period->start_date->toDateString(),
                $period->end_date->toDateString(),
            ])
            ->get();

        $totalMetric = 0;
        foreach ($periodLogs as $pLog) {
            $totalMetric += $this->extractMetricFromLog($pLog, $violationType->detection_mode);
        }

        if (!$this->matchesThreshold($totalMetric, $violationType)) {
            return;
        }

        // منع إصدار أكثر من جزاء تراكمي واحد لنفس الموظف ونفس اللائحة في نفس الفترة المالية
        $alreadyIssuedForPeriod = EmployeeDisciplinaryAction::where('employee_id', $employee->id)
            ->where('violation_type_id', $violationType->id)
            ->where('pay_period_id', $period->id)
            ->whereIn('status', [
                EmployeeDisciplinaryAction::STATUS_PENDING,
                EmployeeDisciplinaryAction::STATUS_APPROVED,
            ])
            ->exists();

        if ($alreadyIssuedForPeriod) {
            return;
        }

        $autoReason = $this->buildAutomatedReason($violationType, $logDate, $totalMetric, true, $period->name);

        $this->recordViolation(
            employee: $employee,
            violationType: $violationType,
            violationDate: $logDate,
            reason: $autoReason,
            attendanceLogId: $log->id,
            payPeriodId: $period->id
        );
    }

    /**
     * التحقق من توافق نوع الرصد الآلي مع سياسة الحضور المعتمدة في عقد الموظف الساري
     */
    protected function isDetectionModeApplicableForContract(
        Employee $employee,
        string $detectionMode,
        Carbon $logDate
    ): bool {
        if ($detectionMode !== ViolationType::DETECTION_ATTENDANCE_EARLY_LEAVE) {
            return true;
        }

        $contract = $employee->contractForDate($logDate)
            ?? $employee->activeContract
            ?? $employee->currentContract;

        $attendanceMode = $contract?->attendance_mode
            ?? config('hr.attendance_mode', 'strict');

        // العقود المعتمدة على بصمة الدخول فقط أو التعبئة التلقائية للوردية لا تخضع لمخالفات الانصراف المبكر
        return !in_array($attendanceMode, ['single_punch', 'auto_shift_pair'], true);
    }

    /**
     * استخراج القيمة المقاسة من سجل الحضور حسب نوع الرصد
     */
    protected function extractMetricFromLog(AttendanceLog $log, string $detectionMode): int
    {
        return match ($detectionMode) {
            ViolationType::DETECTION_ATTENDANCE_LATE => $log->status === 'late'
                ? (int) ($log->delay_minutes ?? 0)
                : 0,
            ViolationType::DETECTION_ATTENDANCE_EARLY_LEAVE => in_array($log->status, ['present', 'late'], true)
                ? (int) ($log->early_leave_minutes ?? 0)
                : 0,
            ViolationType::DETECTION_ATTENDANCE_ABSENT => $log->status === 'absent' ? 1 : 0,
            default => 0,
        };
    }

    /**
     * التحقق من مطابقة القيمة للحد الأدنى والأقصى المقرر في اللائحة
     */
    protected function matchesThreshold(int $value, ViolationType $violationType): bool
    {
        if ($value <= 0) {
            return false;
        }

        if ($violationType->detection_mode === ViolationType::DETECTION_ATTENDANCE_ABSENT) {
            $minThreshold = max(1, (int) $violationType->threshold_minutes);
            return $value >= $minThreshold;
        }

        if ($value < (int) $violationType->threshold_minutes) {
            return false;
        }

        if ($violationType->max_threshold_minutes !== null && $value > (int) $violationType->max_threshold_minutes) {
            return false;
        }

        return true;
    }

    /**
     * صياغة وصف تلقائي واضح للمخالفة المرصودة من البصمة
     */
    protected function buildAutomatedReason(
        ViolationType $violationType,
        Carbon $logDate,
        int $metricValue,
        bool $isCumulative,
        ?string $periodName = null
    ): string {
        $dateStr = $logDate->toDateString();

        if ($violationType->detection_mode === ViolationType::DETECTION_ATTENDANCE_ABSENT) {
            return $isCumulative
                ? "رصد آلي: بلوغ الغياب بدون إذن ({$metricValue} يوم) خلال الفترة ({$periodName})."
                : "رصد آلي: غياب بدون إذن بتاريخ {$dateStr}.";
        }

        $label = $violationType->detection_mode === ViolationType::DETECTION_ATTENDANCE_LATE
            ? 'تأخير صباحي'
            : 'انصراف مبكر';

        return $isCumulative
            ? "رصد آلي: تراكم دقائق {$label} ({$metricValue} دقيقة) خلال الفترة ({$periodName})."
            : "رصد آلي: {$label} بمقدار ({$metricValue} دقيقة) بتاريخ {$dateStr}.";
    }

    /**
     * توليد رقم مرجعي تسلسلي للجزاء التأديبي
     */
    protected function generateActionNumber(): string
    {
        try {
            return $this->sequenceService->generateNumber('hr_disciplinary_action');
        } catch (Throwable) {
            $nextId = ((int) EmployeeDisciplinaryAction::withTrashed()->max('id')) + 1;
            return 'DA-' . now()->format('Y') . '-' . str_pad((string) $nextId, 5, '0', STR_PAD_LEFT);
        }
    }
}