<?php

declare(strict_types=1);

namespace App\Modules\HR\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeDisciplinaryAction extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'hr_employee_disciplinary_actions';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'number',
        'employee_id',
        'violation_type_id',
        'escalation_rule_id',
        'attendance_log_id',
        'pay_period_id',
        'step_order',
        'action_type',
        'deduction_days',
        'deduction_amount',
        'suspension_days',
        'violation_date',
        'expires_at',
        'status',
        'reason',
        'approved_by',
        'approved_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
        'created_by',
    ];

    protected $casts = [
        'step_order'       => 'integer',
        'deduction_days'   => 'decimal:2',
        'deduction_amount' => 'decimal:2',
        'suspension_days'  => 'integer',
        'violation_date'   => 'date',
        'expires_at'       => 'date',
        'approved_at'      => 'datetime',
        'cancelled_at'     => 'datetime',
    ];

    /**
     * الموظف صاحب المخالفة
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * نوع المخالفة / اللائحة المطبقة
     */
    public function violationType(): BelongsTo
    {
        return $this->belongsTo(ViolationType::class, 'violation_type_id');
    }

    /**
     * قاعدة التدرج العقابي المرجعية
     */
    public function escalationRule(): BelongsTo
    {
        return $this->belongsTo(ViolationEscalationRule::class, 'escalation_rule_id');
    }

    /**
     * سجل الحضور المرتبط بالمخالفة الآلية (إن وُجد)
     */
    public function attendanceLog(): BelongsTo
    {
        return $this->belongsTo(AttendanceLog::class, 'attendance_log_id');
    }

    /**
     * الفترة المالية المرتبطة بالمخالفة التراكمية (إن وُجدت)
     */
    public function payPeriod(): BelongsTo
    {
        return $this->belongsTo(PayPeriod::class, 'pay_period_id');
    }

    /**
     * قيد الاستقطاع المالي المرتبط في مدخلات الرواتب
     */
    public function payrollInput(): HasOne
    {
        return $this->hasOne(PayrollInput::class, 'disciplinary_action_id');
    }

    /**
     * المستخدم الذي اعتمد الجزاء
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * المستخدم الذي ألغى الجزاء
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * المستخدم الذي أنشأ السجل
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * جلب الجزاءات المعتمدة فقط
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * جلب الجزاءات المعلقة بانتظار الاعتماد
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * جلب الجزاءات المعتمدة والسارية (التي لم تسقط بالتقادم) في تاريخ محدد
     */
    public function scopeActiveOnDate(Builder $query, Carbon|string $date): Builder
    {
        $dateString = $date instanceof Carbon
            ? $date->toDateString()
            : Carbon::parse($date)->toDateString();

        return $query->where('status', self::STATUS_APPROVED)
            ->where(function (Builder $q) use ($dateString): void {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>=', $dateString);
            });
    }

    /**
     * جلب الجزاءات المؤثرة في سلم التدرج (المعلقة أو المعتمدة التي لم تسقط بالتقادم)
     */
    public function scopeEscalatableOnDate(Builder $query, Carbon|string $date): Builder
    {
        $dateString = $date instanceof Carbon
            ? $date->toDateString()
            : Carbon::parse($date)->toDateString();

        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_APPROVED])
            ->where(function (Builder $q) use ($dateString): void {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>=', $dateString);
            });
    }

    /**
     * التحقق مما إذا كان الجزاء قد سقط بالتقادم في تاريخ معين
     */
    public function isExpiredOn(Carbon|string|null $date = null): bool
    {
        if ($this->status !== self::STATUS_APPROVED || $this->expires_at === null) {
            return false;
        }

        $checkDate = $date === null
            ? Carbon::today()
            : ($date instanceof Carbon ? $date->copy()->startOfDay() : Carbon::parse($date)->startOfDay());

        return $this->expires_at->copy()->startOfDay()->lt($checkDate);
    }

    /**
     * الحالة الفعلية للجزاء (تُرجع expired تلقائياً عند تجاوز تاريخ السقوط)
     */
    public function getEffectiveStatusAttribute(): string
    {
        if ($this->isExpiredOn()) {
            return 'expired';
        }

        return $this->status;
    }

    /**
     * التسمية العربية لنوع العقوبة
     */
    public function getActionTypeLabelAttribute(): string
    {
        return match ($this->action_type) {
            ViolationEscalationRule::ACTION_VERBAL_WARNING   => 'إنذار شفهي',
            ViolationEscalationRule::ACTION_WRITTEN_WARNING  => 'إنذار كتابي',
            ViolationEscalationRule::ACTION_SALARY_DEDUCTION => 'خصم مالي من الراتب',
            ViolationEscalationRule::ACTION_SUSPENSION       => 'إيقاف مؤقت عن العمل',
            ViolationEscalationRule::ACTION_TERMINATION      => 'إنهاء خدمة',
            default                                          => $this->action_type,
        };
    }

    /**
     * التسمية العربية للحالة الفعلية
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->effective_status) {
            self::STATUS_PENDING   => 'بانتظار الاعتماد',
            self::STATUS_APPROVED  => 'معتمد وسارٍ',
            'expired'              => 'منتهي بالتقادم',
            self::STATUS_CANCELLED => 'ملغى',
            default                => $this->status,
        };
    }
}