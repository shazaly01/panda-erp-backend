<?php

declare(strict_types=1);

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WorkingSchedule extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'hr_working_schedules';

    protected $fillable = [
        'name',
        'type',
        'cycle_days',
    ];

    /**
     * الأيام والتفاصيل المرتبطة بهذا القالب
     */
    public function lines(): HasMany
    {
        return $this->hasMany(WorkingScheduleLine::class, 'working_schedule_id');
    }

    /**
     * العقود المرتبطة بهذا القالب
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'working_schedule_id');
    }
}
