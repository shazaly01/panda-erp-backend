<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\Adjustment;
use App\Modules\Inventory\Models\AdjustmentItem;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Models\ProductUnit;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AdjustmentService
{
    public function __construct(
        protected StockMovementService $stockMovementService,
        protected InventoryAccountingService $inventoryAccountingService
    ) {}

    /**
     * إنشاء مستند تسوية / رصيد افتتاحي جديد (يُنشأ دائماً كمسودة أولاً)
     */
    public function createAdjustment(array $data, int $userId): Adjustment
    {
        return DB::transaction(function () use ($data, $userId) {
            $itemsData = $data['items'] ?? [];
            unset($data['items']);

            // فرض الحالة الأولية كمسودة دائماً لضمان مرور مسار الاعتماد بشكل سليم
            $data['created_by'] = $userId;
            $data['status'] = 'draft';

            /** @var Adjustment $adjustment */
            $adjustment = Adjustment::create($data);

            $this->saveAdjustmentItems($adjustment, $itemsData);

            return $adjustment->load(['warehouse', 'items.product', 'items.productUnit', 'items.batch']);
        });
    }

    /**
     * تحديث بيانات مستند تسوية / رصيد افتتاحي (للمسودات فقط)
     */
    public function updateAdjustment(Adjustment $adjustment, array $data): Adjustment
    {
        if ($adjustment->status === 'approved') {
            throw new InvalidArgumentException('لا يمكن تعديل مستند تسوية معتمد، يرجى إنشاء مستند تسوية جديد.');
        }

        return DB::transaction(function () use ($adjustment, $data) {
            $itemsData = $data['items'] ?? null;
            unset($data['items'], $data['status']); // منع تغيير الحالة يدوياً خارج دالة الاعتماد

            $adjustment->update($data);

            if ($itemsData !== null) {
                $adjustment->items()->delete();
                $this->saveAdjustmentItems($adjustment, $itemsData);
            }

            return $adjustment->fresh(['warehouse', 'items.product', 'items.productUnit', 'items.batch']);
        });
    }

    /**
     * اعتماد مستند التسوية وتطبيق الأثر المخزني والمحاسبي
     */
    public function approveAdjustment(Adjustment $adjustment, int $userId): Adjustment
    {
        if ($adjustment->status === 'approved') {
            throw new InvalidArgumentException('هذا المستند معتمد بالفعل.');
        }

        return DB::transaction(function () use ($adjustment, $userId) {
            // 1. تنظيف أي حركات سابقة إن وُجدت
            $this->stockMovementService->clearDocumentMovements($adjustment);

            $totalAdjustmentCost = 0.0;

            // 2. معالجة البنود وتحديث الأرصدة اللحظية لتفادي تغيرات الرصيد أثناء المسودة
            foreach ($adjustment->items as $item) {
                $productUnit = ProductUnit::where('id', $item->product_unit_id)
                    ->where('product_id', $item->product_id)
                    ->first();

                $conversionFactor = $productUnit && (float) $productUnit->conversion_factor > 0
                    ? (float) $productUnit->conversion_factor
                    : 1.0;

                if ($adjustment->type === 'opening_balance') {
                    $quantityToRecord = (float) $item->actual_quantity;
                    $movementType = 'opening_balance';
                    $itemCost = round($quantityToRecord * (float) $item->unit_cost, 4);
                } else {
                    // جلب الرصيد اللحظي الحالي من جدول الأرصدة الحية بالوحدة الأساسية
                    $currentStock = ProductStock::where('warehouse_id', $adjustment->warehouse_id)
                        ->where('product_id', $item->product_id)
                        ->where('batch_id', $item->batch_id)
                        ->first();

                    $baseStockQty = $currentStock ? (float) $currentStock->quantity : 0.0;
                    $currentQtyInItemUnit = $baseStockQty / $conversionFactor;

                    // إعادة حساب الفارق بناءً على الرصيد الفعلي لحظة الاعتماد
                    $difference = (float) $item->actual_quantity - $currentQtyInItemUnit;
                    $quantityToRecord = abs($difference);

                    // إذا كان عجزاً أو إتلافاً/فقداً، يتم تثبيت تكلفة الوحدة الدفترية تلقائياً إن لم تُحدد
                    $unitCost = (float) $item->unit_cost;
                    if ($difference < 0 || in_array($adjustment->type, ['damage', 'loss'], true)) {
                        $product = Product::find($item->product_id);
                        if ($product && (float) $product->cost_price > 0 && $unitCost <= 0) {
                            $unitCost = (float) $product->cost_price * $conversionFactor;
                        }
                    }

                    $itemCost = round($quantityToRecord * $unitCost, 4);

                    // تحديد نوع الحركة المخزنية الدقيق
                    $movementType = match ($adjustment->type) {
                        'damage' => 'damage',
                        'loss'   => 'loss',
                        default  => $difference >= 0 ? 'adjustment_in' : 'adjustment_out',
                    };

                    // تحديث البند بالقيم الحية النهائية
                    $item->update([
                        'current_quantity'    => $currentQtyInItemUnit,
                        'quantity_difference' => $difference,
                        'unit_cost'           => $unitCost,
                        'total_cost'          => $itemCost,
                    ]);
                }

                $totalAdjustmentCost += $itemCost;

                // 3. تسجيل الحركة المخزنية الفعلية إذا كانت الكمية أكبر من صفر
                if ($quantityToRecord > 0) {
                    $this->stockMovementService->recordMovement(
                        productId: $item->product_id,
                        warehouseId: $adjustment->warehouse_id,
                        productUnitId: $item->product_unit_id,
                        movementType: $movementType,
                        quantity: $quantityToRecord,
                        unitCost: (float) $item->unit_cost,
                        reference: $adjustment,
                        batchId: $item->batch_id,
                        notes: $item->notes ?? $adjustment->notes,
                        userId: $userId
                    );
                }
            }

            // 4. تحديث حالة المستند وإجمالي التكلفة
            $adjustment->update([
                'status'      => 'approved',
                'approved_by' => $userId,
                'total_cost'  => round($totalAdjustmentCost, 4),
            ]);

            // 5. إنشاء وترحيل القيد المحاسبي المالي
            $this->inventoryAccountingService->createAdjustmentJournalEntry($adjustment);

            return $adjustment->fresh(['warehouse', 'items.product', 'items.productUnit', 'items.batch', 'journalEntry.details']);
        });
    }

    /**
     * حذف مستند التسوية والبنود التابعة له (للمسودات فقط)
     */
    public function deleteAdjustment(Adjustment $adjustment): bool
    {
        if ($adjustment->status === 'approved') {
            throw new InvalidArgumentException('لا يمكن حذف مستند تسوية معتمد ومرحل في الدفاتر.');
        }

        return DB::transaction(function () use ($adjustment) {
            $this->stockMovementService->clearDocumentMovements($adjustment);

            if ($adjustment->journalEntry) {
                $adjustment->journalEntry->details()->delete();
                $adjustment->journalEntry->forceDelete();
            }

            $adjustment->items()->delete();

            return (bool) $adjustment->delete();
        });
    }

    /**
     * حفظ بنود التسوية وحساب التكاليف الأولية
     */
    protected function saveAdjustmentItems(Adjustment $adjustment, array $itemsData): void
    {
        $totalAdjustmentCost = 0.0;

        foreach ($itemsData as $item) {
            $currentQty = (float) ($item['current_quantity'] ?? 0);
            $actualQty = (float) ($item['actual_quantity'] ?? 0);
            $unitCost = (float) ($item['unit_cost'] ?? 0.0);

            $quantityDiff = $actualQty - $currentQty;

            $costQuantity = $adjustment->type === 'opening_balance' ? $actualQty : abs($quantityDiff);
            $itemTotalCost = round($costQuantity * $unitCost, 4);
            $totalAdjustmentCost += $itemTotalCost;

            AdjustmentItem::create([
                'adjustment_id'       => $adjustment->id,
                'product_id'          => $item['product_id'],
                'product_unit_id'     => $item['product_unit_id'],
                'batch_id'            => $item['batch_id'] ?? null,
                'current_quantity'    => $currentQty,
                'actual_quantity'     => $actualQty,
                'quantity_difference' => $quantityDiff,
                'unit_cost'           => $unitCost,
                'total_cost'          => $itemTotalCost,
                'notes'               => $item['notes'] ?? null,
            ]);
        }

        $adjustment->update([
            'total_cost' => round($totalAdjustmentCost, 4),
        ]);
    }
}