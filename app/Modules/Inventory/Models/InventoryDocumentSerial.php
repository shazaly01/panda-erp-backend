<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryDocumentSerial extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inventory_document_serials';

    protected $fillable = [
        'serial_id',
        'serial_number',
        'document_item_type',
        'document_item_id',
        'replaced_serial_id',
        'previous_status',
        'tare_weight',
        'gross_weight',
        'net_weight',
        'notes',
    ];

    protected $casts = [
        'serial_id' => 'integer',
        'serial_number' => 'string',
        'document_item_id' => 'integer',
        'replaced_serial_id' => 'integer',
        'previous_status' => 'string',
        'tare_weight' => 'decimal:4',
        'gross_weight' => 'decimal:4',
        'net_weight' => 'decimal:4',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * العلاقة متعددة الأشكال مع سطر المستند المرجعي
     * (استلام، صرف، تحويل... إلخ).
     */
    public function documentItem(): MorphTo
    {
        return $this->morphTo('document_item', 'document_item_type', 'document_item_id');
    }

    /**
     * سجل الأسطوانة المرتبط بهذا السطر، إن وجد.
     *
     * يمكن أن يكون serial_id فارغاً في مسودة استلام أسطوانة جديدة.
     * في هذه الحالة تُحفظ بياناتها في حقول المستند:
     * serial_number وtare_weight وgross_weight وnet_weight.
     * ربطها بسجل أسطوانة عند التأكيد مسؤولية خدمة الاستلام.
     */
    public function serial(): BelongsTo
    {
        return $this->belongsTo(StockSerial::class, 'serial_id');
    }

    /**
     * الأسطوانة القديمة المستبدلة إن وجدت.
     *
     * نحافظ على العلاقة الحالية للتوافق مع المستندات والخدمات القائمة.
     * تعديل آلية التبادل مع المورد يأتي في خطوة منفصلة.
     */
    public function replacedSerial(): BelongsTo
    {
        return $this->belongsTo(StockSerial::class, 'replaced_serial_id');
    }
}