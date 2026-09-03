<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Models;

use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\ProductUnit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseBillItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'purchasing_bill_items';

    protected $fillable = [
        'bill_id',
        'purchase_order_item_id',
        'receipt_item_id',
        'product_id',
        'product_unit_id',
        'account_id',
        'quantity',
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
        'bill_id' => 'integer',
        'purchase_order_item_id' => 'integer',
        'receipt_item_id' => 'integer',
        'product_id' => 'integer',
        'product_unit_id' => 'integer',
        'account_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'discount_percentage' => 'decimal:2',
        'discount_amount' => 'decimal:4',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:4',
        'subtotal' => 'decimal:4',
        'total' => 'decimal:4',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(PurchaseBill::class, 'bill_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }

    public function receiptItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseReceiptItem::class, 'receipt_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'product_unit_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class, 'bill_item_id');
    }
}