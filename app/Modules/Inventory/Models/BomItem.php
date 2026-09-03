<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BomItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inventory_bom_items';

    protected $fillable = [
        'bom_id',
        'raw_material_id',
        'product_unit_id',
        'quantity',
        'waste_percentage',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'waste_percentage' => 'decimal:2',
    ];

    /**
     * قائمة المكونات التابع لها العنصر
     */
    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class, 'bom_id');
    }

    /**
     * المادة الخام أو المكون
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
}
