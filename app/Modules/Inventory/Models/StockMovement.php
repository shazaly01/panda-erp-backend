<?php

namespace App\Modules\Inventory\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockMovement extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inventory_stock_movements';

    protected $fillable = [
        'warehouse_id',
        'location_id',
        'product_id',
        'product_unit_id',
        'batch_id',
        'serial_id',
        'movement_type',
        'quantity',
        'unit_cost',
        'total_cost',
        'balance_after_movement',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'warehouse_id' => 'integer',
        'location_id' => 'integer',
        'product_id' => 'integer',
        'product_unit_id' => 'integer',
        'batch_id' => 'integer',
        'serial_id' => 'integer',
        'quantity' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
        'balance_after_movement' => 'decimal:4',
        'created_by' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];



    /**
     * المخزن الذي حدثت فيه الحركة
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    /**
     * الموقع أو الرف الداخلي المخزني
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'location_id');
    }

    /**
     * المنتج المعني بالحركة
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * وحدة قياس الحركة
     */
    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'product_unit_id');
    }

    /**
     * التشغيلة/الباتش المحددة
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class, 'batch_id');
    }

    /**
     * الرقم التسلسلي المحدد (إن وجد)
     */
    public function serial(): BelongsTo
    {
        return $this->belongsTo(StockSerial::class, 'serial_id');
    }

    /**
     * المستخدم الذي أنشأ الحركة
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * العلاقة متعددة الأشكال مع المستند المسبب للحركة (فاتورة، تحويل، تسوية... إلخ)
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}