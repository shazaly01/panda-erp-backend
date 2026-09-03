<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Accounting\Models\Currency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PriceList extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inventory_price_lists';

    protected $fillable = [
        'currency_id',
        'name',
        'code',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

   

    /**
     * العلاقة مع العملة
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    /**
     * أسعار المنتجات المدرجة ضمن هذه القائمة
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class, 'price_list_id');
    }
}
