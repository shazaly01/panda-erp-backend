<?php

declare(strict_types=1);

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OvertimePolicy extends Model
{
    protected $table = 'hr_overtime_policies';

    protected $fillable = [
        'name',
        'working_days_per_month', // مقسوم الأيام (مثال: 30)
        'working_hours_per_day',  // مقسوم الساعات (مثال: 8)
        'regular_rate',           // معامل الأيام العادية (مثال: 1.5)
        'weekend_rate',           // معامل أيام العطلة الأسبوعية (مثال: 2.0)
        'holiday_rate',           // معامل العطلات الرسمية (مثال: 2.0 أو 3.0)
        'is_daily_basis',         // هل يعامل الإضافي كيوم كامل؟ (true/false)
        'hours_to_day_threshold', // إذا كان is_daily_basis صحيحاً، كم ساعة تعادل يوماً؟ (مثلاً: 5)
    ];

    protected $casts = [
        'working_days_per_month' => 'integer',
        'working_hours_per_day' => 'integer',
        'regular_rate' => 'decimal:2',
        'weekend_rate' => 'decimal:2',
        'holiday_rate' => 'decimal:2',
        'is_daily_basis' => 'boolean',
        'hours_to_day_threshold' => 'integer',
    ];

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }
}
