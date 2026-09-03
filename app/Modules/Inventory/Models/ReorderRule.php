<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReorderRule extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inventory_reorder_rules';

    protected $fillable = [
        'warehouse_id',
        'product_id',
        'min_quantity',
        'max_quantity',
        'reorder_quantity',
        'is_active',
    ];

    protected $casts = [
        'warehouse_id' => 'integer',
        'product_id' => 'integer',
        'min_quantity' => 'decimal:4',
        'max_quantity' => 'decimal:4',
        'reorder_quantity' => 'decimal:4',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

  
    /**
     * العلاقة مع المستودع المحدد للقاعدة
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    /**
     * العلاقة مع المنتج المعني بإعادة الطلب
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}