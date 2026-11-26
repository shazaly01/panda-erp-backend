<?php

declare(strict_types=1);

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ViolationType extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'hr_violation_types';

    public const DETECTION_MANUAL = 'manual';
    public const DETECTION_ATTENDANCE_LATE = 'attendance_late';
    public const DETECTION_ATTENDANCE_EARLY_LEAVE = 'attendance_early_leave';
    public const DETECTION_ATTENDANCE_ABSENT = 'attendance_absent';

    public const TRIGGER_PER_INCIDENT = 'per_incident';
    public const TRIGGER_CUMULATIVE_MONTHLY = 'cumulative_monthly';

    protected $fillable = [
        'name',
        'code',
        'description',
        'detection_mode',
        'trigger_mode',
        'threshold_minutes',
        'max_threshold_minutes',
        'requires_approval',
        'is_active',
    ];

    protected $casts = [
        'threshold_minutes'     => 'integer',
        'max_threshold_minutes' => 'integer',
        'requires_approval'     => 'boolean',
        'is_active'             => 'boolean',
    ];

    /**
     * درجات سلم التدرج العقابي المرتبطة بهذه اللائحة مرتبة تصاعدياً
     */
    public function escalationRules(): HasMany
    {
        return $this->hasMany(ViolationEscalationRule::class, 'violation_type_id')
            ->orderBy('step_order');
    }

    /**
     * سجل المخالفات والجزاءات المسجلة على الموظفين تحت هذا النوع
     */
    public function disciplinaryActions(): HasMany
    {
        return $this->hasMany(EmployeeDisciplinaryAction::class, 'violation_type_id');
    }

    /**
     * جلب اللوائح النشطة فقط
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * التحقق مما إذا كانت المخالفة تُرصد آلياً من نظام الحضور والانصراف
     */
    public function isAttendanceAutomated(): bool
    {
        return in_array($this->detection_mode, [
            self::DETECTION_ATTENDANCE_LATE,
            self::DETECTION_ATTENDANCE_EARLY_LEAVE,
            self::DETECTION_ATTENDANCE_ABSENT,
        ], true);
    }

    /**
     * التسمية العربية لطريقة الرصد
     */
    public function getDetectionModeLabelAttribute(): string
    {
        return match ($this->detection_mode) {
            self::DETECTION_MANUAL                 => 'رصد يدوي / إداري',
            self::DETECTION_ATTENDANCE_LATE        => 'آلي - التأخير الصباحي',
            self::DETECTION_ATTENDANCE_EARLY_LEAVE => 'آلي - الانصراف المبكر',
            self::DETECTION_ATTENDANCE_ABSENT      => 'آلي - الغياب بدون إذن',
            default                                => $this->detection_mode,
        };
    }

    /**
     * التسمية العربية لآلية التفعيل
     */
    public function getTriggerModeLabelAttribute(): string
    {
        return match ($this->trigger_mode) {
            self::TRIGGER_PER_INCIDENT       => 'لكل مخالفة / حادثة',
            self::TRIGGER_CUMULATIVE_MONTHLY => 'تراكمي خلال الفترة المالية',
            default                          => $this->trigger_mode,
        };
    }
}