<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockSerial extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inventory_serials';

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'location_id',
        'batch_id',
        'serial_number',
        'status',
        'notes',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'warehouse_id' => 'integer',
        'location_id' => 'integer',
        'batch_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

 
    /**
     * المنتج التابع له السيريال
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * المستودع الذي يتواجد به السيريال حالياً
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    /**
     * الموقع/الرف الداخلي الذي يتواجد به السيريال
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'location_id');
    }

    /**
     * التشغيلة/الباتش المربوط بها السيريال (إن وجد)
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class, 'batch_id');
    }

    /**
     * الحركات المخزنية المسجلة على هذا السيريال
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'serial_id');
    }
}