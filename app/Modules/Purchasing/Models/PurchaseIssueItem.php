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
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseIssueItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'purchasing_issue_items';

    protected $fillable = [
        'issue_id',
        'requisition_item_id',
        'product_id',
        'product_unit_id',
        'location_id',
        'batch_id',
        'quantity',
        'unit_cost',
        'total_cost',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'issue_id' => 'integer',
        'requisition_item_id' => 'integer',
        'product_id' => 'integer',
        'product_unit_id' => 'integer',
        'location_id' => 'integer',
        'batch_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'quantity' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * إذن الصرف التابع له هذا البند
     */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(PurchaseIssue::class, 'issue_id');
    }

    /**
     * بند طلب الاحتياج المقابل (إن وجد)
     */
    public function requisitionItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisitionItem::class, 'requisition_item_id');
    }

    /**
     * الصنف المصروف
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * وحدة القياس المستخدمة في الصرف
     */
    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'product_unit_id');
    }

    /**
     * الموقع أو الرف المخزني المصروف منه
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'location_id');
    }

    /**
     * التشغيلة أو الدفعة المنصرفة
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
     * الأرقام التسلسلية وقراءات الأوزان الموثقة لهذا البند
     */
    public function serials(): MorphMany
    {
        return $this->morphMany(InventoryDocumentSerial::class, 'document_item', 'document_item_type', 'document_item_id');
    }
}