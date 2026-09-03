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

class PurchaseOrderItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'purchasing_order_items';

    protected $fillable = [
        'purchase_order_id',
        'requisition_item_id',
        'product_id',
        'product_unit_id',
        'quantity',
        'received_quantity',
        'billed_quantity',
        'unit_price',
        'discount_percentage',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'subtotal',
        'total',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'purchase_order_id' => 'integer',
        'requisition_item_id' => 'integer',
        'product_id' => 'integer',
        'product_unit_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'quantity' => 'decimal:4',
        'received_quantity' => 'decimal:4',
        'billed_quantity' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'discount_percentage' => 'decimal:2',
        'discount_amount' => 'decimal:4',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:4',
        'subtotal' => 'decimal:4',
        'total' => 'decimal:4',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function requisitionItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisitionItem::class, 'requisition_item_id');
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

    public function receiptItems(): HasMany
    {
        return $this->hasMany(PurchaseReceiptItem::class, 'purchase_order_item_id');
    }

    public function billItems(): HasMany
    {
        return $this->hasMany(PurchaseBillItem::class, 'purchase_order_item_id');
    }
}