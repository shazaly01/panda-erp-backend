<?php

declare(strict_types=1);

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternetVoucher extends Model
{
    use SoftDeletes;

    protected $table = 'hr_internet_vouchers';

    // تعطيل الزيادة التلقائية الافتراضية لتوافقها مع DECIMAL(18,0)
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'code',
        'capacity',
        'status',
        'employee_id',
        'attendance_log_id',
        'assigned_at',
        'expires_at'
    ];

    protected $casts = [
        'id' => 'decimal:0',
        'employee_id' => 'decimal:0',
        'attendance_log_id' => 'decimal:0',
        'assigned_at' => 'datetime',
        'expires_at' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function attendanceLog(): BelongsTo
    {
        return $this->belongsTo(AttendanceLog::class);
    }
}
