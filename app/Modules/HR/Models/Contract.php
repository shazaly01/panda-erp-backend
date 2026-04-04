<?php

declare(strict_types=1);

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contract extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'salary_structure_id',
        'basic_salary',
        'start_date',
        'end_date',
        'is_active',
        'attachment_path'
    ];

    protected $casts = [
        'basic_salary' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function salaryStructure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class);
    }
}
