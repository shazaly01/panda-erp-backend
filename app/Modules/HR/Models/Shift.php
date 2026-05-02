<?php

declare(strict_types=1);

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use SoftDeletes;

    protected $table = 'hr_shifts';

    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'grace_period_minutes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // 🔥 العلاقة الجديدة (أين تُستخدم هذه الوردية في قوالب الجداول)
    public function scheduleLines(): HasMany
    {
        return $this->hasMany(WorkingScheduleLine::class, 'shift_id');
    }
}
