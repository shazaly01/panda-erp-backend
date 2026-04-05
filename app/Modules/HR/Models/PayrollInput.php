<?php

declare(strict_types=1);

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class PayrollInput extends Model
{
    use SoftDeletes;

    protected $table = 'hr_payroll_inputs';

    protected $fillable = [
        'employee_id',
        'type', // bonus, penalty, allowance, deduction
        'amount',
        'date',
        'reason',
        'is_processed',
        'payroll_batch_id',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
        'is_processed' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
