<?php

declare(strict_types=1);

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User; // استدعاء موديل المستخدم الافتراضي للنظام

class ShiftOverride extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'hr_shift_overrides';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'date',
        'original_shift_id',
        'new_shift_id',
        'reason',
        'approved_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date' => 'date',
    ];

    /**
     * الموظف صاحب التجاوز
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * الوردية الأصلية (القديمة)
     */
    public function originalShift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'original_shift_id');
    }

    /**
     * الوردية الجديدة (الحالية)
     */
    public function newShift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'new_shift_id');
    }

    /**
     * المدير الذي اعتمد التجاوز
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
