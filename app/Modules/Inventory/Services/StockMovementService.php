<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Models\ProductUnit;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StockMovementService
{
    /**
     * أنواع الحركات المضيفة للمخزون (Inflows)
     */
    protected array $inflowTypes = [
        'in',
        'transfer_in',
        'adjustment_in',
        'production_in',
        'purchase',
        'opening_balance',
    ];

    /**
     * أنواع الحركات الخافضة للمخزون (Outflows)
     */
    protected array $outflowTypes = [
        'out',
        'transfer_out',
        'adjustment_out',
        'production_out',
        'sales',
        'damage',
        'loss',
    ];

    /**
     * تسجيل حركة مخزنية جديدة وتحديث المخزون اللحظي بالوحدة الأساسية
     */
    public function recordMovement(
        int $productId,
        int $warehouseId,
        int $productUnitId,
        string $movementType,
        float $quantity,
        float $unitCost = 0.0,
        ?Model $reference = null,
        ?int $locationId = null,
        ?int $batchId = null,
        ?int $serialId = null,
        ?string $notes = null,
        ?int $userId = null
    ): StockMovement {
        return DB::transaction(function () use (
            $productId,
            $warehouseId,
            $productUnitId,
            $movementType,
            $quantity,
            $unitCost,
            $reference,
            $locationId,
            $batchId,
            $serialId,
            $notes,
            $userId
        ) {
            // 1. جلب معامل التحويل وتأمينه
            $productUnit = ProductUnit::where('id', $productUnitId)
                ->where('product_id', $productId)
                ->firstOrFail();

            $conversionFactor = (float) $productUnit->conversion_factor;
            if ($conversionFactor <= 0) {
                $conversionFactor = 1.0;
            }

            $baseQuantity = $quantity * $conversionFactor;

            // 2. التحقق من اتجاه الحركة
            $isAdd = in_array($movementType, $this->inflowTypes, true);
            $isDeduct = in_array($movementType, $this->outflowTypes, true);

            if (!$isAdd && !$isDeduct) {
                throw new InvalidArgumentException("نوع الحركة المخزنية غير معروف: {$movementType}");
            }

            // 3. جلب أو إنشاء سجل الرصيد الحي مع القفل الحصري لمنع تعارض التزامن (Race Conditions)
            $stock = ProductStock::withTrashed()
                ->where('warehouse_id', $warehouseId)
                ->where('product_id', $productId)
                ->where('location_id', $locationId)
                ->where('batch_id', $batchId)
                ->lockForUpdate()
                ->first();

            if (!$stock) {
                $stock = ProductStock::create([
                    'warehouse_id'      => $warehouseId,
                    'product_id'        => $productId,
                    'location_id'       => $locationId,
                    'batch_id'          => $batchId,
                    'quantity'          => 0.0000,
                    'reserved_quantity' => 0.0000,
                ]);
            }

            if ($stock->trashed()) {
                $stock->restore();
            }

            // 4. تحديث الكمية الحية بالوحدة الأساسية
            if ($isAdd) {
                $stock->increment('quantity', $baseQuantity);
            } else {
                $stock->decrement('quantity', $baseQuantity);
            }

            $stock->refresh();

            // 5. حساب التكلفة الإجمالية وتسجيل الحركة
            $totalCost = $quantity * $unitCost;

            $movement = new StockMovement([
                'warehouse_id'           => $warehouseId,
                'location_id'            => $locationId,
                'product_id'             => $productId,
                'product_unit_id'        => $productUnitId,
                'batch_id'               => $batchId,
                'serial_id'              => $serialId,
                'movement_type'          => $movementType,
                'quantity'               => $quantity,
                'unit_cost'              => $unitCost,
                'total_cost'             => $totalCost,
                'balance_after_movement' => $stock->quantity,
                'created_by'             => $userId ?? Auth::id(),
                'notes'                  => $notes,
            ]);

            if ($reference !== null) {
                $movement->reference()->associate($reference);
            }

            $movement->save();

            return $movement;
        });
    }

    /**
     * إلغاء وعكس أثر الحركات المخزنية المرتبطة بمستند معين وحذفها
     */
    public function clearDocumentMovements(Model $reference): void
    {
        DB::transaction(function () use ($reference) {
            $movements = StockMovement::where('reference_type', $reference->getMorphClass())
                ->where('reference_id', $reference->getKey())
                ->with('productUnit')
                ->get();

            foreach ($movements as $movement) {
                $conversionFactor = 1.0;
                if ($movement->productUnit) {
                    $conversionFactor = (float) $movement->productUnit->conversion_factor;
                    if ($conversionFactor <= 0) {
                        $conversionFactor = 1.0;
                    }
                }

                $baseQuantity = (float) $movement->quantity * $conversionFactor;
                $isAdd = in_array($movement->movement_type, $this->inflowTypes, true);

                $stock = ProductStock::withTrashed()
                    ->where('warehouse_id', $movement->warehouse_id)
                    ->where('product_id', $movement->product_id)
                    ->where('location_id', $movement->location_id)
                    ->where('batch_id', $movement->batch_id)
                    ->lockForUpdate()
                    ->first();

                if ($stock) {
                    if ($isAdd) {
                        $stock->decrement('quantity', $baseQuantity);
                    } else {
                        $stock->increment('quantity', $baseQuantity);
                    }
                }

                // الحذف الحقيقي والتطهير التام للحركة القديمة
                $movement->forceDelete();
            }
        });
    }
}