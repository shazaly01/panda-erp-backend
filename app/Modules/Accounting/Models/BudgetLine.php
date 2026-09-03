<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'budget_id',
        'account_id',
        'cost_center_id',
        'period_start',
        'period_end',
        'planned_amount',
        'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'planned_amount' => 'decimal:4',
    ];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }
}