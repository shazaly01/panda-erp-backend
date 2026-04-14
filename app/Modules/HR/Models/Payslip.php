<?php

declare(strict_types=1);

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends Model
{
    protected $fillable = [
        'payroll_batch_id', 'employee_id', 'basic_salary',
        'total_allowances', 'total_deductions', 'net_salary', 'details'
    ];

    protected $casts = [
        'details' => 'array',
        'basic_salary' => 'decimal:0',
        'net_salary' => 'decimal:2',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PayrollBatch::class, 'payroll_batch_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
