<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Inventory\Enums\StockSerialStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockSerial extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inventory_serials';

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'location_id',
        'batch_id',
        'serial_number',
        'tare_weight',
        'gross_weight',
        'current_gas_weight',
        'status',
        'notes',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'warehouse_id' => 'integer',
        'location_id' => 'integer',
        'batch_id' => 'integer',
        'tare_weight' => 'decimal:4',
        'gross_weight' => 'decimal:4',
        'current_gas_weight' => 'decimal:4',
        'status' => StockSerialStatus::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * المنتج التابع له السيريال (أسطوانة الكلور)
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * المستودع الذي يتواجد به السيريال حالياً
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    /**
     * الموقع/الرف الداخلي الذي يتواجد به السيريال
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'location_id');
    }

    /**
     * التشغيلة/الباتش المربوط بها السيريال (إن وجد)
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class, 'batch_id');
    }

    /**
     * الحركات المخزنية المسجلة على هذا السيريال
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'serial_id');
    }

    /**
     * سجل توثيق السيريال في بنود المستندات المختلفة
     */
    public function documentSerials(): HasMany
    {
        return $this->hasMany(InventoryDocumentSerial::class, 'serial_id');
    }

    /**
     * حساب صافي وزن الغاز بناءً على قراءة الميزان الإجمالية الحالية
     */
    public function calculateNetWeight(float $grossWeight): float
    {
        $tare = (float) ($this->tare_weight ?? 0.0);
        return max(0.0, round($grossWeight - $tare, 4));
    }

    /**
     * تحديث حالة الأسطوانة إلى ممتلئة بالمستودع
     */
    public function markAsInStock(
        int $warehouseId,
        ?int $locationId = null,
        ?float $grossWeight = null,
        ?float $gasWeight = null,
        ?string $notes = null
    ): bool {
        $calculatedGas = $gasWeight;
        if ($calculatedGas === null && $grossWeight !== null) {
            $calculatedGas = $this->calculateNetWeight($grossWeight);
        }

        $updatedNotes = $this->appendNotes($notes);

        return $this->update([
            'status' => StockSerialStatus::IN_STOCK,
            'warehouse_id' => $warehouseId,
            'location_id' => $locationId,
            'gross_weight' => $grossWeight ?? $this->gross_weight,
            'current_gas_weight' => $calculatedGas ?? $this->current_gas_weight,
            'notes' => $updatedNotes,
        ]);
    }

    /**
     * تحديث حالة الأسطوانة إلى قيد التشغيل بالمحطة على خط المعالجة
     */
    public function markAsInService(?string $notes = null): bool
    {
        $updatedNotes = $this->appendNotes($notes);

        return $this->update([
            'status' => StockSerialStatus::IN_SERVICE,
            'notes' => $updatedNotes,
        ]);
    }

    /**
     * تحديث حالة الأسطوانة إلى فارغة بعد اكتمال استهلاك الغاز بالمحطة
     */
    public function markAsEmpty(
        int $warehouseId,
        ?int $locationId = null,
        ?string $notes = null
    ): bool {
        $updatedNotes = $this->appendNotes($notes);

        return $this->update([
            'status' => StockSerialStatus::EMPTY,
            'warehouse_id' => $warehouseId,
            'location_id' => $locationId,
            'gross_weight' => $this->tare_weight,
            'current_gas_weight' => 0.0000,
            'notes' => $updatedNotes,
        ]);
    }

    /**
     * تحويل حالة الأسطوانة إلى خارجة للتعبئة لدى المورد
     */
    public function markAsWithVendor(?string $notes = null): bool
    {
        $updatedNotes = $this->appendNotes($notes);

        return $this->update([
            'status' => StockSerialStatus::WITH_VENDOR,
            'warehouse_id' => null,
            'location_id' => null,
            'notes' => $updatedNotes,
        ]);
    }

    /**
     * تحويل حالة الأسطوانة إلى تحت الفحص والصيانة الدورية
     */
    public function markAsInMaintenance(?string $notes = null): bool
    {
        $updatedNotes = $this->appendNotes($notes);

        return $this->update([
            'status' => StockSerialStatus::IN_MAINTENANCE,
            'notes' => $updatedNotes,
        ]);
    }

    /**
     * تحويل حالة الأسطوانة إلى مستبدلة لدى المورد
     */
    public function markAsReplaced(?string $notes = null): bool
    {
        $updatedNotes = $this->appendNotes($notes);

        return $this->update([
            'status' => StockSerialStatus::REPLACED,
            'warehouse_id' => null,
            'location_id' => null,
            'current_gas_weight' => 0.0000,
            'notes' => $updatedNotes,
        ]);
    }

    /**
     * تحويل حالة الأسطوانة إلى تالفة / مكهنة خارج الخدمة نهائياً
     */
    public function markAsScrapped(?string $notes = null): bool
    {
        $updatedNotes = $this->appendNotes($notes);

        return $this->update([
            'status' => StockSerialStatus::SCRAPPED,
            'warehouse_id' => null,
            'location_id' => null,
            'current_gas_weight' => 0.0000,
            'notes' => $updatedNotes,
        ]);
    }

    /**
     * تحديث كمية الغاز الصافية الحالية عند رصد استهلاك أو فاقد وزني
     */
    public function updateGasWeight(float $currentGrossWeight, ?string $notes = null): bool
    {
        $netGas = $this->calculateNetWeight($currentGrossWeight);
        $updatedNotes = $this->appendNotes($notes);

        return $this->update([
            'gross_weight' => $currentGrossWeight,
            'current_gas_weight' => $netGas,
            'notes' => $updatedNotes,
        ]);
    }

    /**
     * دمج الملاحظات الجديدة مع السجل النصي السابق
     */
    protected function appendNotes(?string $newNotes): ?string
    {
        if (empty($newNotes)) {
            return $this->notes;
        }

        if (empty($this->notes)) {
            return trim($newNotes);
        }

        return trim($this->notes . PHP_EOL . $newNotes);
    }

    /**
     * نطاق الأسطوانات المتاحة والممتلئة بالمستودع
     */
    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('status', StockSerialStatus::IN_STOCK);
    }

    /**
     * نطاق الأسطوانات قيد التشغيل بمحطة المعالجة
     */
    public function scopeInService(Builder $query): Builder
    {
        return $query->where('status', StockSerialStatus::IN_SERVICE);
    }

    /**
     * نطاق الأسطوانات الفارغة بانتظار الشحن للتعبئة
     */
    public function scopeEmpty(Builder $query): Builder
    {
        return $query->where('status', StockSerialStatus::EMPTY);
    }

    /**
     * نطاق الأسطوانات المتواجدة لدى المورد لإعادة التعبئة
     */
    public function scopeWithVendor(Builder $query): Builder
    {
        return $query->where('status', StockSerialStatus::WITH_VENDOR);
    }
}