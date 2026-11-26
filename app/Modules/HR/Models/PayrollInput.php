<?php

declare(strict_types=1);

namespace App\Modules\HR\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'disciplinary_action_id',
        'created_by',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'date'         => 'date',
        'is_processed' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payrollBatch(): BelongsTo
    {
        return $this->belongsTo(PayrollBatch::class, 'payroll_batch_id');
    }

    public function disciplinaryAction(): BelongsTo
    {
        return $this->belongsTo(EmployeeDisciplinaryAction::class, 'disciplinary_action_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}