<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;


use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transfer extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inventory_transfers';

    protected $fillable = [
        'transfer_number',
        'from_warehouse_id',
        'to_warehouse_id',
        'transfer_date',
        'status',
        'notes',
        'created_by',
        'approved_by',
    ];

    protected $casts = [
        'from_warehouse_id' => 'integer',
        'to_warehouse_id' => 'integer',
        'created_by' => 'integer',
        'approved_by' => 'integer',
        'transfer_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

   
    /**
     * المستودع المصدر
     */
    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    /**
     * المستودع الوجهة
     */
    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    /**
     * المستخدم منشئ طلب التحويل
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * المستخدم المعتمد لطلب التحويل
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * بنود أمر التحويل
     */
    public function items(): HasMany
    {
        return $this->hasMany(TransferItem::class, 'transfer_id');
    }

    /**
     * الحركات المخزنية المرتبطة بأمر التحويل (Polymorphic)
     */
    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }
}