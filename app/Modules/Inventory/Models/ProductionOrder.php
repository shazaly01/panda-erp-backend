<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inventory_production_orders';

    protected $fillable = [
        'order_number',
        'bom_id',
        'product_id',
        'raw_materials_warehouse_id',
        'finished_goods_warehouse_id',
        'produced_batch_id',
        'planned_quantity',
        'actual_quantity',
        'additional_costs',
        'status',
        'production_date',
        'notes',
        'created_by',
        'approved_by',
    ];

    protected $casts = [
        'bom_id' => 'integer',
        'product_id' => 'integer',
        'raw_materials_warehouse_id' => 'integer',
        'finished_goods_warehouse_id' => 'integer',
        'produced_batch_id' => 'integer',
        'planned_quantity' => 'decimal:4',
        'actual_quantity' => 'decimal:4',
        'additional_costs' => 'decimal:4',
        'production_date' => 'date',
        'created_by' => 'integer',
        'approved_by' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];



    /**
     * قائمة المكونات (BOM) المعتمدة للأمر
     */
    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class, 'bom_id');
    }

    /**
     * المنتج النهائي المراد إنتاجه
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * المستودع المسحوب منه المواد الخام
     */
    public function rawMaterialsWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'raw_materials_warehouse_id');
    }

    /**
     * المستودع المستهدف لاستلام المنتج النهائي
     */
    public function finishedGoodsWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'finished_goods_warehouse_id');
    }

    /**
     * التشغيلة/الباتش المستهدفة للمنتج النهائي
     */
    public function producedBatch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class, 'produced_batch_id');
    }

    /**
     * المستخدم منشئ أمر الإنتاج
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * المستخدم المعتمد لأمر الإنتاج
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * بنود واستهلاكات أمر الإنتاج
     */
    public function items(): HasMany
    {
        return $this->hasMany(ProductionOrderItem::class, 'production_order_id');
    }

    /**
     * الحركات المخزنية المرتبطة بأمر الإنتاج (Polymorphic)
     */
    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }
}