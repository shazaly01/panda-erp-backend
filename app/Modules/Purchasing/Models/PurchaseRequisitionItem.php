<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Models;

use App\Models\User;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\ProductUnit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseRequisitionItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'purchasing_requisition_items';

    protected $fillable = [
        'requisition_id',
        'product_id',
        'product_unit_id',
        'quantity_requested',
        'quantity_approved',
        'quantity_ordered',
        'estimated_unit_cost',
        'specifications',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'requisition_id' => 'integer',
        'product_id' => 'integer',
        'product_unit_id' => 'integer',
        'quantity_requested' => 'decimal:4',
        'quantity_approved' => 'decimal:4',
        'quantity_ordered' => 'decimal:4',
        'estimated_unit_cost' => 'decimal:4',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class, 'requisition_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'product_unit_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'requisition_item_id');
    }
}