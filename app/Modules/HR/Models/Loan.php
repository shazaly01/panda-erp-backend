<?php

declare(strict_types=1);

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;

class Loan extends Model
{
    use SoftDeletes;

    protected $table = 'hr_loans';

    protected $fillable = [
        'employee_id',
        'amount',
        'reason',
        'voucher_id', // رقم سند الصرف المحاسبي
        'deduction_start_date',
        'installments_count',
        'status',
        'approved_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'deduction_start_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * الأقساط المرتبطة بهذه السلفة
     */
    public function installments(): HasMany
    {
        return $this->hasMany(LoanInstallment::class);
    }
}
