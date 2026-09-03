<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Models;

use App\Models\User;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\ProductUnit;
use App\Modules\Inventory\Models\StockBatch;
use App\Modules\Inventory\Models\WarehouseLocation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReceiptItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'purchasing_receipt_items';

    protected $fillable = [
        'receipt_id',
        'purchase_order_item_id',
        'product_id',
        'product_unit_id',
        'location_id',
        'batch_id',
        'quantity_received',
        'quantity_accepted',
        'quantity_rejected',
        'unit_cost',
        'total_cost',
        'rejection_reason',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'receipt_id' => 'integer',
        'purchase_order_item_id' => 'integer',
        'product_id' => 'integer',
        'product_unit_id' => 'integer',
        'location_id' => 'integer',
        'batch_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'quantity_received' => 'decimal:4',
        'quantity_accepted' => 'decimal:4',
        'quantity_rejected' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseReceipt::class, 'receipt_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'product_unit_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'location_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class, 'batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function billItems(): HasMany
    {
        return $this->hasMany(PurchaseBillItem::class, 'receipt_item_id');
    }
}