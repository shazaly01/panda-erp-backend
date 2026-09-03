<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\Accounting\Enums\BudgetStatus;
use App\Modules\Accounting\Enums\BudgetControlMode;
use App\Modules\Accounting\Enums\BudgetPeriodType;
use App\Models\User;

class Budget extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'fiscal_year_id',
        'period_type',
        'control_mode',
        'status',
        'start_date',
        'end_date',
        'total_amount',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'period_type' => BudgetPeriodType::class,
        'control_mode' => BudgetControlMode::class,
        'status' => BudgetStatus::class,
        'start_date' => 'date',
        'end_date' => 'date',
        'total_amount' => 'decimal:4',
        'approved_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function (Budget $budget) {
            if ($budget->status === BudgetStatus::Approved || $budget->status === BudgetStatus::Active) {
                abort(422, 'لا يمكن حذف موازنة معتمدة أو نشطة. يجب تغيير حالتها إلى مسودة أولاً.');
            }
        });
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BudgetLine::class);
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isDraft(): bool
    {
        return $this->status === BudgetStatus::Draft;
    }

    public function isApproved(): bool
    {
        return $this->status === BudgetStatus::Approved;
    }

    public function isActive(): bool
    {
        return $this->status === BudgetStatus::Active;
    }

    public function isClosed(): bool
    {
        return $this->status === BudgetStatus::Closed;
    }
}