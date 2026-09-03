<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inventory_units';

    protected $fillable = [
        'name',
        'symbol',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * العلاقة مع وحدات المنتجات المخصصة
     */
    public function productUnits(): HasMany
    {
        return $this->hasMany(ProductUnit::class, 'unit_id');
    }
}
