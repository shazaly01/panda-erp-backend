<?php

declare(strict_types=1);

namespace App\Modules\HR\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrLeavePass extends Model
{
    use SoftDeletes;

    protected $table = 'hr_leave_passes';

    protected $fillable = [
        'employee_id',
        'date',
        'reason',
        'requested_leave_at',
        'requested_return_at',
        'actual_leave_at',
        'actual_return_at',
        'pass_code',
        'status',
        'approved_by',
        'gate_checked_out_by',
        'gate_checked_in_by',
    ];

    protected $casts = [
        'date' => 'date',
        'actual_leave_at' => 'datetime',
        'actual_return_at' => 'datetime',
    ];

    /**
     * الموظف صاحب إذن الخروج المؤقت
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * المشرف المحاسب أو الإداري الذي اعتمد الطلب
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    /**
     * فرد أمن البوابة الخارجية الذي أثبت وبصم خروج الموظف فعلياً
     */
    public function gateCheckedOutBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gate_checked_out_by');
    }

    /**
     * فرد أمن البوابة الخارجية الذي أثبت وبصم عودة الموظف لداخل الأسوار
     */
    public function gateCheckedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gate_checked_in_by');
    }
}
