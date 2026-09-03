<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Models\User;
use App\Modules\Accounting\Models\Account;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inventory_warehouses';

    protected $fillable = [
        'code',
        'name',
        'phone',
        'address',
        'manager_id',
        'account_id',
        'is_active',
    ];

    protected $casts = [
        'manager_id' => 'integer',
        'account_id' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * مدير المستودع
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * الحساب المالي للمخزون المرتبط بهذا المستودع
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * المواقع والرفوف الداخلية للمستودع
     */
    public function locations(): HasMany
    {
        return $this->hasMany(WarehouseLocation::class, 'warehouse_id');
    }

    /**
     * أرصدة المنتجات داخل هذا المستودع
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class, 'warehouse_id');
    }

    /**
     * سجل الحركات المخزنية المرتطبة بالمستودع
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'warehouse_id');
    }

    /**
     * قواعد إعادة الطلب المعرفة لهذا المستودع
     */
    public function reorderRules(): HasMany
    {
        return $this->hasMany(ReorderRule::class, 'warehouse_id');
    }

    /**
     * الأرقام التسلسلية المتواجدة بهذا المستودع
     */
    public function serials(): HasMany
    {
        return $this->hasMany(StockSerial::class, 'warehouse_id');
    }
}