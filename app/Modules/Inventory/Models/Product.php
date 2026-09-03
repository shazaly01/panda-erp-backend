<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inventory_products';

    protected $fillable = [
        'category_id',
        'name',
        'sku',
        'description',
        'type',
        'inventory_policy',
        'tracking_type',
        'valuation_method',
        'cost_price',
        'is_active',
    ];

    protected $casts = [
        'category_id' => 'integer',
        'cost_price' => 'decimal:4',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

  

    /**
     * العلاقة مع التصنيف
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * الوحدات المربوطة بالمنتج مع معاملات التحويل
     */
    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class, 'product_id');
    }

    /**
     * أكواد الباركود الخاصة بالمنتج
     */
    public function barcodes(): HasMany
    {
        return $this->hasMany(ProductBarcode::class, 'product_id');
    }

    /**
     * أسعار المنتج في قوائم الأسعار المختلفة
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class, 'product_id');
    }

    /**
     * الأرصدة الحالية للمنتج عبر المخازن
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class, 'product_id');
    }

    /**
     * سجل الحركات المخزنية للمنتج
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'product_id');
    }

    /**
     * قوائم المكونات (BOM) التي يُنتج منها هذا المنتج
     */
    public function boms(): HasMany
    {
        return $this->hasMany(Bom::class, 'product_id');
    }

    /**
     * استهلاكات هذا المنتج كمادة خام داخل قوائم المكونات
     */
    public function bomUsage(): HasMany
    {
        return $this->hasMany(BomItem::class, 'raw_material_id');
    }

    /**
     * قواعد إعادة الطلب المعرفة لهذا المنتج
     */
    public function reorderRules(): HasMany
    {
        return $this->hasMany(ReorderRule::class, 'product_id');
    }

    /**
     * التشغيلات والدفعات المسجلة لهذا المنتج
     */
    public function batches(): HasMany
    {
        return $this->hasMany(StockBatch::class, 'product_id');
    }

    /**
     * الأرقام التسلسلية المربوطة بهذا المنتج
     */
    public function serials(): HasMany
    {
        return $this->hasMany(StockSerial::class, 'product_id');
    }
}