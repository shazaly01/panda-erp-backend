<?php

declare(strict_types=1);

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    use SoftDeletes;

    protected $table = 'hr_leave_types';

    protected $fillable = [
        'name',
        'code',
        'is_paid',
        'max_days_per_year',
        'requires_approval',
    ];

    protected $casts = [
        'is_paid' => 'boolean',
        'requires_approval' => 'boolean',
        'max_days_per_year' => 'decimal:2',
    ];

    /**
     * أرصدة الموظفين المرتبطة بهذا النوع
     */
    public function balances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    /**
     * طلبات الإجازة المقدمة تحت هذا النوع
     */
    public function requests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
