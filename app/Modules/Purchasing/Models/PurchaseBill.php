<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Models;

use App\Models\User;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Core\Models\Partner;
use App\Modules\Purchasing\Enums\BillStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseBill extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'purchasing_bills';

    protected $fillable = [
        'bill_number',
        'supplier_bill_number',
        'supplier_id',
        'purchase_order_id',
        'receipt_id',
        'currency_id',
        'exchange_rate',
        'bill_date',
        'due_date',
        'status',
        'subtotal',
        'discount_type',
        'discount_value',
        'discount_amount',
        'tax_amount',
        'shipping_cost',
        'total_amount',
        'paid_amount',
        'remaining_amount',
        'posted_by',
        'posted_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'bill_date' => 'date',
        'due_date' => 'date',
        'posted_at' => 'datetime',
        'status' => BillStatus::class,
        'supplier_id' => 'integer',
        'purchase_order_id' => 'integer',
        'receipt_id' => 'integer',
        'currency_id' => 'integer',
        'posted_by' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'exchange_rate' => 'decimal:4',
        'subtotal' => 'decimal:4',
        'discount_value' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'shipping_cost' => 'decimal:4',
        'total_amount' => 'decimal:4',
        'paid_amount' => 'decimal:4',
        'remaining_amount' => 'decimal:4',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'supplier_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseReceipt::class, 'receipt_id');
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
        return $this->hasMany(PurchaseBillItem::class, 'bill_id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class, 'bill_id');
    }

    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'reference');
    }
}