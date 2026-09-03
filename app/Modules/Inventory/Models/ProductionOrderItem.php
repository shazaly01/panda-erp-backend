<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionOrderItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inventory_production_order_items';

    protected $fillable = [
        'production_order_id',
        'raw_material_id',
        'product_unit_id',
        'batch_id',
        'planned_quantity',
        'actual_quantity',
        'unit_cost',
        'total_cost',
        'notes',
    ];

    protected $casts = [
        'planned_quantity' => 'decimal:4',
        'actual_quantity' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
    ];

    /**
     * أمر الإنتاج التابع له البند
     */
    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    /**
     * المادة الخام المستهلكة
     */
    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'raw_material_id');
    }

    /**
     * الوحدة المستخدمة للمادة الخام
     */
    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'product_unit_id');
    }

    /**
     * التشغيلة/الباتش المسحوب منها (إن وجد)
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class, 'batch_id');
    }
}
