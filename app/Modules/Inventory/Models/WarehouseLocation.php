<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarehouseLocation extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inventory_warehouse_locations';

    protected $fillable = [
        'warehouse_id',
        'parent_id',
        'code',
        'name',
        'type',
        'is_active',
    ];

    protected $casts = [
        'warehouse_id' => 'integer',
        'parent_id' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * العلاقة مع المستودع التابع له هذا الموقع/الرف
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    /**
     * الموقع الأب (في حالة الهيكلية الشجرية: ممر -> رف -> صندوق)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * المواقع الفرعية التابعة لهذا الموقع
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * أرصدة المنتجات المخزنة في هذا الموقع المحدّد
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class, 'location_id');
    }

    /**
     * الحركات المخزنية المسجلة على هذا الموقع المحدّد
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'location_id');
    }
}