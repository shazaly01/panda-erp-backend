<?php

declare(strict_types=1);

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_batch_id',
        'employee_id',
        'basic_salary',
        'total_allowances',
        'total_deductions',
        'net_salary',
        'details',
    ];

    protected $casts = [
        'basic_salary'     => 'decimal:2',
        'total_allowances' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_salary'       => 'decimal:2',
        'details'          => 'array',
    ];

    /**
     * دفعة مسير الرواتب التابع لها هذا السجل
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(PayrollBatch::class, 'payroll_batch_id');
    }

    /**
     * الموظف صاحب قسيمة الراتب
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}