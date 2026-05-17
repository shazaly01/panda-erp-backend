<?php

declare(strict_types=1);

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLog extends Model
{
    use SoftDeletes;

    protected $table = 'hr_attendance_logs';

    protected $fillable = [
        'employee_id',
        'shift_id',
        'calendar_exception_id',
        'date',
        'check_in',
        'check_out',
        'delay_minutes',
        'early_leave_minutes',
        'overtime_minutes',
        'status',
        // الحقول الجنائية والتدقيق (Audit Fields)
        'is_manual_override',
        'approved_by',
        'override_reason',
    ];

    protected $casts = [
        'date' => 'date',
        'is_manual_override' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function calendarException(): BelongsTo
    {
        return $this->belongsTo(CalendarException::class, 'calendar_exception_id');
    }

    /**
     * الموظف/المشرف الذي قام بتعديل السجل يدوياً
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }
}
