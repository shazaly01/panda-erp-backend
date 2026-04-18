<?php

declare(strict_types=1);

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\HR\Enums\SalaryFrequency;
// 👈 تم حذف استيراد Illuminate\Validation\Rules\Enum من هنا

class Contract extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'salary_structure_id',
        'overtime_policy_id',
        'pay_group_id',
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
        // 👈 التصحيح هنا: نمرر الكلاس مباشرة بدون وضعه داخل دالة Enum()
        'salary_frequency' => SalaryFrequency::class,
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function salaryStructure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class);
    }

    public function overtimePolicy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OvertimePolicy::class, 'overtime_policy_id');
    }

    public function payGroup(): BelongsTo
    {
        return $this->belongsTo(PayGroup::class, 'pay_group_id');
    }
}
