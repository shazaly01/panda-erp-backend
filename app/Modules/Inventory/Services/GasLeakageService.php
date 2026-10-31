<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Enums\StockSerialStatus;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockSerial;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GasLeakageService
{
    public function __construct(
        protected readonly StockMovementService $stockMovementService
    ) {}

    /**
     * توثيق تسريب أو فاقد وزني لأسطوانة غاز وترحيل الأثر المخزني
     *
     * @param int $serialId معرف الأسطوانة
     * @param float $newGrossWeight الوزن الإجمالي القائم الجديد المسجل على الميزان (كجم)
     * @param int|null $warehouseId المستودع الحالي المسجل به الفاقد
     * @param string|null $reason سبب الفاقد أو ملابسات التسريب
     * @param int|null $userId معرف المستخدم المسؤول
     * @return array<string, mixed>
     */
    public function recordLeakage(
        int $serialId,
        float $newGrossWeight,
        ?int $warehouseId = null,
        ?string $reason = null,
        ?int $userId = null
    ): array {
        return DB::transaction(function () use (
            $serialId,
            $newGrossWeight,
            $warehouseId,
            $reason,
            $userId
        ): array {
            /** @var StockSerial $serial */
            $serial = StockSerial::with(['product.units'])->lockForUpdate()->findOrFail($serialId);

            // التحقق من أن حالة الأسطوانة تسمح بوجود غاز وتسجيل فاقد
            if (in_array($serial->status, [
                StockSerialStatus::WITH_VENDOR,
                StockSerialStatus::EMPTY,
                StockSerialStatus::REPLACED,
                StockSerialStatus::SCRAPPED,
            ], true)) {
                throw new InvalidArgumentException(
                    "لا يمكن تسجيل تسريب وزني لأسطوانة في حالة ({$serial->status->label()})."
                );
            }

            $tareWeight = (float) ($serial->tare_weight ?? 0.0);
            if ($tareWeight <= 0) {
                throw new InvalidArgumentException(
                    "الأسطوانة رقم ({$serial->serial_number}) لا تحتوي على وزن فارغ مسجل (Tare Weight) لحساب صافي الغاز بدقة."
                );
            }

            if ($newGrossWeight < $tareWeight) {
                throw new InvalidArgumentException(
                    "الوزن الجديد المسجل على الميزان ({$newGrossWeight} كجم) أقل من الوزن الفارغ للأسطوانة ({$tareWeight} كجم)."
                );
            }

            $currentGasWeight = (float) ($serial->current_gas_weight ?? 0.0);
            $newGasWeight = max(0.0, round($newGrossWeight - $tareWeight, 4));

            if ($newGasWeight >= $currentGasWeight) {
                throw new InvalidArgumentException(
                    "صافي الوزن الجديد ({$newGasWeight} كجم) لا يعكس وجود نقص أو تسريب مقارنة بالوزن الحالي المسجل ({$currentGasWeight} كجم)."
                );
            }

            // حساب كمية الغاز المتسربة بالكيلوجرام
            $leakedQuantity = round($currentGasWeight - $newGasWeight, 4);

            $targetWarehouseId = $warehouseId ?? $serial->warehouse_id;
            if (! $targetWarehouseId) {
                throw new InvalidArgumentException(
                    "يجب تحديد المستودع الذي حدث فيه الفاقد أو نقل الأسطوانة إليه أولاً."
                );
            }

            // تحديد وحدة الصنف الأساسية (كيلوجرام)
            $productUnit = $serial->product?->units?->firstWhere('is_base_unit', true)
                ?? $serial->product?->units?->first();

            if (! $productUnit) {
                throw new InvalidArgumentException("لا توجد وحدة قياس معرفة للصنف المرتبط بهذه الأسطوانة.");
            }

            $unitCost = (float) ($serial->product?->cost_price ?? 0.0);

            // 1. تسجيل حركة خروج المخزون كفاقد/تسريب بالكيلوجرام
            $movementNote = sprintf(
                "تسريب وفاقد غاز للأسطوانة (%s): نقص الوزن من (%.4f كجم) إلى (%.4f كجم). السبب: %s",
                $serial->serial_number,
                $currentGasWeight,
                $newGasWeight,
                $reason ?: 'تسريب أو تلف صمام الأسطوانة'
            );

            /** @var StockMovement $movement */
            $movement = $this->stockMovementService->recordMovement(
                productId: (int) $serial->product_id,
                warehouseId: (int) $targetWarehouseId,
                productUnitId: (int) $productUnit->id,
                movementType: 'loss',
                quantity: $leakedQuantity,
                unitCost: $unitCost,
                reference: $serial,
                locationId: $serial->location_id ? (int) $serial->location_id : null,
                batchId: $serial->batch_id ? (int) $serial->batch_id : null,
                serialId: $serial->id,
                notes: $movementNote,
                userId: $userId
            );

            // 2. تحديث قراءات الوزن وسجل الملاحظات في سجل الأسطوانة
            $serial->updateGasWeight(
                currentGrossWeight: $newGrossWeight,
                notes: "تم إثبات تسريب وزني بمقدار ({$leakedQuantity} كجم) بحركة مخزنية رقم #{$movement->id}"
            );

            // 3. إذا نفد الغاز بالكامل جراء التسريب، يتم تحويل حالة الأسطوانة إلى فارغة
            if ($newGasWeight <= 0.0000) {
                $serial->markAsEmpty(
                    warehouseId: (int) $targetWarehouseId,
                    locationId: $serial->location_id ? (int) $serial->location_id : null,
                    notes: "فرغت الأسطوانة بالكامل إثر التسريب والفاقد المخزني رقم #{$movement->id}"
                );
            }

            return [
                'serial_id' => $serial->id,
                'serial_number' => $serial->serial_number,
                'previous_gas_weight' => $currentGasWeight,
                'new_gas_weight' => $newGasWeight,
                'leaked_quantity' => $leakedQuantity,
                'movement_id' => $movement->id,
                'is_empty' => ($newGasWeight <= 0.0000),
            ];
        });
    }
}