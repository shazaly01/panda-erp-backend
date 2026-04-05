<?php

declare(strict_types=1);

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricPunch extends Model
{
    // لا نستخدم SoftDeletes هنا لأنها بيانات آلة غير قابلة للتعديل
    protected $table = 'hr_biometric_punches';

    protected $fillable = [
        'employee_id',
        'punch_time',
        'punch_type',
        'device_id',
        'is_processed',
    ];

    protected $casts = [
        'punch_time' => 'datetime',
        'is_processed' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
