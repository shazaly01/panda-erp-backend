<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Models\User;
use App\Modules\Accounting\Models\JournalEntry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Adjustment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inventory_adjustments';

    protected $fillable = [
        'warehouse_id',
        'adjustment_number',
        'adjustment_date',
        'type',
        'status',
        'total_cost',
        'notes',
        'created_by',
        'approved_by',
    ];

    protected $casts = [
        'warehouse_id' => 'integer',
        'created_by' => 'integer',
        'approved_by' => 'integer',
        'total_cost' => 'decimal:4',
        'adjustment_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * المستودع الذي أُجريت فيه التسوية
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    /**
     * بنود وثيقة التسوية المخزنية
     */
    public function items(): HasMany
    {
        return $this->hasMany(AdjustmentItem::class, 'adjustment_id');
    }

    /**
     * المستخدم منشئ التسوية
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * المستخدم المعتمد للتسوية
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * الحركات المخزنية المرتبطة بطلب التسوية (Polymorphic)
     */
    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }

    /**
     * القيد المحاسبي المرتبط بوثيقة التسوية (Polymorphic)
     */
    public function journalEntry(): MorphOne
    {
        return $this->morphOne(JournalEntry::class, 'reference');
    }
}