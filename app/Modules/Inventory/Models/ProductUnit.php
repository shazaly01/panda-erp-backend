<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductUnit extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inventory_product_units';

    protected $fillable = [
        'product_id',
        'unit_id',
        'conversion_factor',
        'is_base_unit',
        'is_purchase_unit',
        'is_sale_unit',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'unit_id' => 'integer',
        'conversion_factor' => 'decimal:6',
        'is_base_unit' => 'boolean',
        'is_purchase_unit' => 'boolean',
        'is_sale_unit' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * المنتج التابع له هذا الربط
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * الوحدة القياسية المرتبطة
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    /**
     * أكواد الباركود المربوطة بهذه الوحدة المحددة
     */
    public function barcodes(): HasMany
    {
        return $this->hasMany(ProductBarcode::class, 'product_unit_id');
    }

    /**
     * الأسعار المسجلة لهذه الوحدة عبر قوائم الأسعار
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class, 'product_unit_id');
    }
}