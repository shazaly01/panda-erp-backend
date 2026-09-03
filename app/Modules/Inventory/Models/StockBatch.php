<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockBatch extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inventory_stock_batches';

    protected $fillable = [
        'product_id',
        'batch_number',
        'manufacturing_date',
        'expiry_date',
        'is_active',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'manufacturing_date' => 'date',
        'expiry_date' => 'date',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];



    /**
     * العلاقة مع المنتج
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * العلاقة مع أرصدة المستودعات لهذه التشغيلة
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class, 'batch_id');
    }

    /**
     * الأرقام التسلسلية المرتبطة بهذه التشغيلة/الباتش
     */
    public function serials(): HasMany
    {
        return $this->hasMany(StockSerial::class, 'batch_id');
    }
}