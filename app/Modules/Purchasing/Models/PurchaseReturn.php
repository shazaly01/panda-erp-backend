<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Models;

use App\Models\User;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Core\Models\Partner;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Purchasing\Enums\PurchaseReturnStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReturn extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'purchasing_returns';

    protected $fillable = [
        'return_number',
        'bill_id',
        'supplier_id',
        'warehouse_id',
        'currency_id',
        'exchange_rate',
        'return_date',
        'status',
        'return_reason',
        'subtotal',
        'tax_amount',
        'total_amount',
        'posted_by',
        'posted_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'return_date' => 'date',
        'posted_at' => 'datetime',
        'status' => PurchaseReturnStatus::class,
        'bill_id' => 'integer',
        'supplier_id' => 'integer',
        'warehouse_id' => 'integer',
        'currency_id' => 'integer',
        'posted_by' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'exchange_rate' => 'decimal:4',
        'subtotal' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'total_amount' => 'decimal:4',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(PurchaseBill::class, 'bill_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'supplier_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class, 'return_id');
    }

    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'reference');
    }

    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }
}