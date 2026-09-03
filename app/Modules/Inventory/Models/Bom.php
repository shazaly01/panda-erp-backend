<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bom extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inventory_boms';

    protected $fillable = [
        'product_id',
        'product_unit_id',
        'code',
        'name',
        'quantity',
        'is_default',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];


    /**
     * المنتج النهائي المستهدف بالتصنيع
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * وحدة إنتاج المنتج النهائي
     */
    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'product_unit_id');
    }

    /**
     * المواد الخام والمكونات الداخلة في هذه القائمة
     */
    public function items(): HasMany
    {
        return $this->hasMany(BomItem::class, 'bom_id');
    }

    /**
     * أوامر الإنتاج والتجميع التي تعتمد على هذه القائمة
     */
    public function productionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class, 'bom_id');
    }
}
