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
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReturnItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'purchasing_return_items';

    protected $fillable = [
        'return_id',
        'bill_item_id',
        'product_id',
        'product_unit_id',
        'location_id',
        'batch_id',
        'quantity',
        'unit_price',
        'tax_rate',
        'tax_amount',
        'subtotal',
        'total',
        'reason',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'return_id' => 'integer',
        'bill_item_id' => 'integer',
        'product_id' => 'integer',
        'product_unit_id' => 'integer',
        'location_id' => 'integer',
        'batch_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:4',
        'subtotal' => 'decimal:4',
        'total' => 'decimal:4',
    ];

    public function return(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class, 'return_id');
    }

    public function billItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseBillItem::class, 'bill_item_id');
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
}