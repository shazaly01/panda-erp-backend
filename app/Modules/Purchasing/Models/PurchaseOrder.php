<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Models;

use App\Models\User;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Core\Models\Partner;
use App\Modules\Purchasing\Enums\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'purchasing_orders';

    protected $fillable = [
        'order_number',
        'supplier_id',
        'requisition_id',
        'currency_id',
        'exchange_rate',
        'order_date',
        'expected_delivery_date',
        'payment_terms',
        'status',
        'subtotal',
        'discount_type',
        'discount_value',
        'discount_amount',
        'tax_amount',
        'shipping_cost',
        'total_amount',
        'confirmed_by',
        'confirmed_at',
        'notes',
        'terms_and_conditions',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
        'confirmed_at' => 'datetime',
        'status' => PurchaseOrderStatus::class,
        'supplier_id' => 'integer',
        'requisition_id' => 'integer',
        'currency_id' => 'integer',
        'confirmed_by' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'exchange_rate' => 'decimal:4',
        'subtotal' => 'decimal:4',
        'discount_value' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'shipping_cost' => 'decimal:4',
        'total_amount' => 'decimal:4',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'supplier_id');
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class, 'requisition_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
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
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(PurchaseReceipt::class, 'purchase_order_id');
    }

    public function bills(): HasMany
    {
        return $this->hasMany(PurchaseBill::class, 'purchase_order_id');
    }
}