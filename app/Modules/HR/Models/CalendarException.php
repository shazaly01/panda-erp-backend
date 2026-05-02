<?php

declare(strict_types=1);

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CalendarException extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'hr_calendar_exceptions';

    protected $fillable = [
        'name',
        'type',
        'start_date',
        'end_date',
        'treat_as_overtime_if_worked',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'treat_as_overtime_if_worked' => 'boolean',
    ];

    /**
     * سجلات الحضور التي تأثرت أو تم احتسابها بناءً على هذا الاستثناء
     */
    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'calendar_exception_id');
    }
}
