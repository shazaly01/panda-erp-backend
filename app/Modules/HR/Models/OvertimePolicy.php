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
        'working_days_per_month',      // مقسوم الأيام (مثال: 30)
        'working_hours_per_day',       // مقسوم الساعات (مثال: 8)
        'regular_rate',                // معامل الأيام العادية (مثال: 1.5)
        'weekend_rate',                // معامل أيام العطلة الأسبوعية (مثال: 2.0)
        'holiday_rate',                // معامل العطلات الرسمية (مثال: 2.0 أو 3.0)
        'is_daily_basis',              // هل يعامل الإضافي كيوم كامل؟ (true/false)
        'hours_to_day_threshold',      // إذا كان is_daily_basis صحيحاً، كم ساعة تعادل يوماً؟ (مثلاً: 5)
        'overtime_source',             // مصدر احتساب الإضافي ('punch' أو 'supervisor')
        'wage_factor',                 // نسبة وعاء الراتب لاحتساب أجر الساعة (مثال: 0.7600 أو 1.0000)
        'fixed_monthly_hours',         // ساعات العمل الشهرية الثابتة إن وجدت (مثال: 176)
        'tax_rate',                    // نسبة ضريبة العمل الإضافي (مثال: 15.00 أو 0.00)
        'tax_exempt_employment_types', // الفئات المعفاة من ضريبة الإضافي (JSON Array)
    ];

    protected $casts = [
        'working_days_per_month'      => 'integer',
        'working_hours_per_day'       => 'integer',
        'regular_rate'                => 'decimal:2',
        'weekend_rate'                => 'decimal:2',
        'holiday_rate'                => 'decimal:2',
        'is_daily_basis'              => 'boolean',
        'hours_to_day_threshold'      => 'integer',
        'overtime_source'             => 'string',
        'wage_factor'                 => 'decimal:4',
        'fixed_monthly_hours'         => 'integer',
        'tax_rate'                    => 'decimal:2',
        'tax_exempt_employment_types' => 'array',
    ];

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }
}