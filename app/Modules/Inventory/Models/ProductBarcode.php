<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductBarcode extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inventory_product_barcodes';

    protected $fillable = [
        'product_id',
        'product_unit_id',
        'barcode',
    ];

 

    /**
     * المنتج المرتبط بهذا الباركود
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * الوحدة المحددة المرتبطة بهذا الباركود
     */
    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'product_unit_id');
    }
}