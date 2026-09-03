<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransferItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inventory_transfer_items';

    protected $fillable = [
        'transfer_id',
        'product_id',
        'batch_id',
        'product_unit_id',
        'from_location_id',
        'to_location_id',
        'quantity',
        'unit_cost',
        'total_cost',
        'notes',
    ];

    protected $casts = [
        'transfer_id' => 'integer',
        'product_id' => 'integer',
        'batch_id' => 'integer',
        'product_unit_id' => 'integer',
        'from_location_id' => 'integer',
        'to_location_id' => 'integer',
        'quantity' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * العلاقة مع أمر التحويل الرئيسي
     */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class, 'transfer_id');
    }

    /**
     * العلاقة مع المنتج
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * العلاقة مع التشغيلة / الباتش
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class, 'batch_id');
    }

    /**
     * العلاقة مع وحدة قياس المنتج
     */
    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'product_unit_id');
    }

    /**
     * الموقع المصدر الداخلي (الرف/الممر)
     */
    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'from_location_id');
    }

    /**
     * الموقع الوجهة الداخلي (الرف/الممر)
     */
    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'to_location_id');
    }
}