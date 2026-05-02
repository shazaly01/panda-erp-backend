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
        'shift_id', // 👈 سيبقى موجوداً لتوثيق الوردية التي تم العمل بها فعلياً في ذلك اليوم
        'calendar_exception_id', // 👈 تمت الإضافة لتوثيق الطوارئ أو الأعياد
        'date',
        'check_in',
        'check_out',
        'delay_minutes',
        'early_leave_minutes',
        'overtime_minutes',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    // 🔥 العلاقة الجديدة
    public function calendarException(): BelongsTo
    {
        return $this->belongsTo(CalendarException::class, 'calendar_exception_id');
    }
}
