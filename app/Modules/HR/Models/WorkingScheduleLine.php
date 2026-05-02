<?php

declare(strict_types=1);

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WorkingScheduleLine extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'hr_working_schedule_lines';

    protected $fillable = [
        'working_schedule_id',
        'day_number',
        'shift_id',
    ];

    /**
     * القالب الأساسي الذي ينتمي إليه هذا الخط
     */
    public function workingSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkingSchedule::class, 'working_schedule_id');
    }

    /**
     * الوردية المخصصة لهذا اليوم (قد تكون Null إذا كان يوم راحة)
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }
}
