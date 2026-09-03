<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Models;

use App\Models\User;
use App\Modules\Core\Models\Partner;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Purchasing\Enums\ReceiptStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReceipt extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'purchasing_receipts';

    protected $fillable = [
        'receipt_number',
        'purchase_order_id',
        'supplier_id',
        'warehouse_id',
        'receipt_date',
        'status',
        'supplier_delivery_note',
        'waybill_number',
        'received_by',
        'received_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'received_at' => 'datetime',
        'status' => ReceiptStatus::class,
        'purchase_order_id' => 'integer',
        'supplier_id' => 'integer',
        'warehouse_id' => 'integer',
        'received_by' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'supplier_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
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
        return $this->hasMany(PurchaseReceiptItem::class, 'receipt_id');
    }

    public function bills(): HasMany
    {
        return $this->hasMany(PurchaseBill::class, 'receipt_id');
    }

    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }
}