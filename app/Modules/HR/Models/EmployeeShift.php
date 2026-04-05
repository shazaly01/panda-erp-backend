<?php

declare(strict_types=1);

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeShift extends Model
{
    use SoftDeletes;

    protected $table = 'hr_employee_shifts';

    protected $fillable = [
        'employee_id',
        'shift_id',
        'start_date',
        'end_date',
        'weekend_days',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'weekend_days' => 'array', // يحول الـ JSON في الداتا بيز إلى مصفوفة برمجية آلياً
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
