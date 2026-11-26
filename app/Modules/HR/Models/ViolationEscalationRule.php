<?php

declare(strict_types=1);

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ViolationEscalationRule extends Model
{
    use HasFactory;

    protected $table = 'hr_violation_escalation_rules';

    public const ACTION_VERBAL_WARNING = 'verbal_warning';
    public const ACTION_WRITTEN_WARNING = 'written_warning';
    public const ACTION_SALARY_DEDUCTION = 'salary_deduction';
    public const ACTION_SUSPENSION = 'suspension';
    public const ACTION_TERMINATION = 'termination';

    public const DEDUCTION_NONE = 'none';
    public const DEDUCTION_DAYS = 'days';
    public const DEDUCTION_FIXED = 'fixed';
    public const DEDUCTION_PERCENTAGE = 'percentage';

    protected $fillable = [
        'violation_type_id',
        'step_order',
        'action_type',
        'deduction_type',
        'deduction_value',
        'suspension_days',
        'validity_months',
        'notes',
    ];

    protected $casts = [
        'step_order'      => 'integer',
        'deduction_value' => 'decimal:4',
        'suspension_days' => 'integer',
        'validity_months' => 'integer',
    ];

    /**
     * اللائحة / نوع المخالفة الأم
     */
    public function violationType(): BelongsTo
    {
        return $this->belongsTo(ViolationType::class, 'violation_type_id');
    }

    /**
     * التحقق مما إذا كانت هذه الدرجة يترتب عليها أثر مالي في الرواتب
     */
    public function hasFinancialDeduction(): bool
    {
        return $this->deduction_type !== self::DEDUCTION_NONE && (float) $this->deduction_value > 0;
    }

    /**
     * التسمية العربية لنوع العقوبة
     */
    public function getActionTypeLabelAttribute(): string
    {
        return match ($this->action_type) {
            self::ACTION_VERBAL_WARNING   => 'إنذار شفهي',
            self::ACTION_WRITTEN_WARNING  => 'إنذار كتابي',
            self::ACTION_SALARY_DEDUCTION => 'خصم مالي من الراتب',
            self::ACTION_SUSPENSION       => 'إيقاف مؤقت عن العمل',
            self::ACTION_TERMINATION      => 'توصية بإنهاء الخدمة',
            default                       => $this->action_type,
        };
    }
}