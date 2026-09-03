<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductStock extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inventory_product_stocks';

    protected $fillable = [
        'warehouse_id',
        'location_id',
        'product_id',
        'batch_id',
        'quantity',
        'reserved_quantity',
    ];

    protected $casts = [
        'warehouse_id' => 'integer',
        'location_id' => 'integer',
        'product_id' => 'integer',
        'batch_id' => 'integer',
        'quantity' => 'decimal:4',
        'reserved_quantity' => 'decimal:4',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];



    /**
     * العلاقة مع المستودع
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    /**
     * العلاقة مع الموقع/الرف الداخلي للمستودع
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'location_id');
    }

    /**
     * العلاقة مع المنتج
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * العلاقة مع تشغيلة/باتش المخزون
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class, 'batch_id');
    }
}