<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Models;

use App\Models\User;
use App\Modules\Inventory\Models\InventoryDocumentSerial;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\ProductUnit;
use App\Modules\Inventory\Models\StockBatch;
use App\Modules\Inventory\Models\WarehouseLocation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReceiptItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'purchasing_receipt_items';

    protected $fillable = [
        'receipt_id',
        'purchase_order_item_id',
        'product_id',
        'product_unit_id',
        'location_id',
        'batch_id',
        'quantity_received',
        'quantity_accepted',
        'quantity_rejected',
        'unit_cost',
        'total_cost',
        'rejection_reason',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'receipt_id' => 'integer',
        'purchase_order_item_id' => 'integer',
        'product_id' => 'integer',
        'product_unit_id' => 'integer',
        'location_id' => 'integer',
        'batch_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'quantity_received' => 'decimal:4',
        'quantity_accepted' => 'decimal:4',
        'quantity_rejected' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * سند الاستلام التابع له هذا البند
     */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseReceipt::class, 'receipt_id');
    }

    /**
     * بند أمر الشراء المقابل (إن وجد)
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }

    /**
     * الصنف المعني بالاستلام
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * وحدة القياس المستخدمة في البند
     */
    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'product_unit_id');
    }

    /**
     * الموقع أو الرف المخزني داخل المستودع
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'location_id');
    }

    /**
     * التشغيلة أو الدفعة المرتبطة
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class, 'batch_id');
    }

    /**
     * المستخدم الذي أنشأ السجل
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * المستخدم الذي قام بآخر تحديث
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * بنود فواتير الشراء المرتبطة بهذا البند
     */
    public function billItems(): HasMany
    {
        return $this->hasMany(PurchaseBillItem::class, 'receipt_item_id');
    }

    /**
     * الأرقام التسلسلية وقراءات الأوزان الموثقة لهذا البند
     */
    public function serials(): MorphMany
    {
        return $this->morphMany(InventoryDocumentSerial::class, 'document_item', 'document_item_type', 'document_item_id');
    }
}