<?php

declare(strict_types=1);

namespace App\Modules\Sales\Models;

use App\Models\User;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PosShift extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pos_shifts';

    protected $fillable = [
        'shift_number',
        'user_id',
        'branch_id',
        'warehouse_id',
        'status',
        'opening_cash',
        'closing_cash',
        'actual_cash',
        'difference',
        'total_sales',
        'total_returns',
        'total_cash',
        'total_card',
        'opened_at',
        'closed_at',
        'closed_by',
        'notes',
    ];

    protected $casts = [
        'opening_cash' => 'decimal:2',
        'closing_cash' => 'decimal:2',
        'actual_cash' => 'decimal:2',
        'difference' => 'decimal:2',
        'total_sales' => 'decimal:2',
        'total_returns' => 'decimal:2',
        'total_cash' => 'decimal:2',
        'total_card' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class, 'shift_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalesInvoicePayment::class, 'shift_id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SalesReturn::class, 'shift_id');
    }

    public function returnPayments(): HasMany
    {
        return $this->hasMany(SalesReturnPayment::class, 'shift_id');
    }
}