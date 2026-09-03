<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdjustmentItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inventory_adjustment_items';

    protected $fillable = [
        'adjustment_id',
        'product_id',
        'product_unit_id',
        'batch_id',
        'current_quantity',
        'actual_quantity',
        'quantity_difference',
        'unit_cost',
        'total_cost',
        'notes',
    ];

    protected $casts = [
        'adjustment_id' => 'integer',
        'product_id' => 'integer',
        'product_unit_id' => 'integer',
        'batch_id' => 'integer',
        'current_quantity' => 'decimal:4',
        'actual_quantity' => 'decimal:4',
        'quantity_difference' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * التسوية المخزنية التابع لها البند
     */
    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(Adjustment::class, 'adjustment_id');
    }

    /**
     * المنتج المحدد
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * الوحدة المستخدمة في التسوية
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
}