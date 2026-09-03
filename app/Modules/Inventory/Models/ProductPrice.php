<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductPrice extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inventory_product_prices';

    protected $fillable = [
        'price_list_id',
        'product_id',
        'product_unit_id',
        'price',
        'min_quantity',
    ];

    protected $casts = [
        'price' => 'decimal:4',
        'min_quantity' => 'decimal:4',
    ];

    /**
     * قائمة الأسعار التابع لها هذا السعر
     */
    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class, 'price_list_id');
    }

    /**
     * المنتج المسعر
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * الوحدة القياسية المحددة للسعر
     */
    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'product_unit_id');
    }
}
