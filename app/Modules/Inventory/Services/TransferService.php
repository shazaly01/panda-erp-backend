<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Models\ProductUnit;
use App\Modules\Inventory\Models\Transfer;
use App\Modules\Inventory\Models\TransferItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TransferService
{
    public function __construct(
        protected StockMovementService $stockMovementService,
        protected InventoryAccountingService $inventoryAccountingService
    ) {}

    /**
     * إنشاء أمر تحويل مخزني جديد
     */
    public function createTransfer(array $data, int $userId): Transfer
    {
        return DB::transaction(function () use ($data, $userId) {
            $itemsData = $data['items'] ?? [];
            unset($data['items']);

            // توليد رقم التحويل آلياً من جدول التسلسلات إن لم يُمرر
            if (empty($data['transfer_number'])) {
                $data['transfer_number'] = $this->generateTransferNumber();
            }

            $data['created_by'] = $userId;
            $data['status'] = $data['status'] ?? 'pending';

            /** @var Transfer $transfer */
            $transfer = Transfer::create($data);

            $this->saveTransferItems($transfer, $itemsData);

            // إذا أُنشئ المستند بحالة مكتملة مباشرة
            if ($transfer->status === 'completed') {
                $transfer->status = 'pending'; // إعادة ضبط مؤقتة ليمر عبر دورة الإكمال القياسية
                return $this->completeTransfer($transfer, $userId);
            }

            return $transfer->load([
                'fromWarehouse',
                'toWarehouse',
                'items.product',
                'items.productUnit',
                'items.fromLocation',
                'items.toLocation',
                'items.batch',
            ]);
        });
    }

    /**
     * تحديث بيانات أمر التحويل وبنوده (للمسودات والطلبات غير المكتملة فقط)
     */
    public function updateTransfer(Transfer $transfer, array $data): Transfer
    {
        if ($transfer->status === 'completed') {
            throw new InvalidArgumentException('لا يمكن تعديل أمر تحويل مكتمل ومرحل مخزنياً.');
        }

        return DB::transaction(function () use ($transfer, $data) {
            $itemsData = $data['items'] ?? null;
            unset($data['items']);

            // منع تعديل الحالة مباشرة عبر التحديث اليدوي
            unset($data['status'], $data['approved_by']);

            $transfer->update($data);

            if ($itemsData !== null) {
                $transfer->items()->delete();
                $this->saveTransferItems($transfer, $itemsData);
            }

            return $transfer->fresh([
                'fromWarehouse',
                'toWarehouse',
                'items.product',
                'items.productUnit',
                'items.fromLocation',
                'items.toLocation',
                'items.batch',
            ]);
        });
    }

    /**
     * اعتماد وإكمال عملية التحويل وتطبيق الأثر المخزني والمالي
     */
    public function completeTransfer(Transfer $transfer, int $userId): Transfer
    {
        if ($transfer->status === 'completed') {
            throw new InvalidArgumentException('أمر التحويل مكتمل ومرحل بالفعل.');
        }

        if ($transfer->status === 'cancelled') {
            throw new InvalidArgumentException('لا يمكن إكمال أمر تحويل ملغي.');
        }

        return DB::transaction(function () use ($transfer, $userId) {
            // 1. تنظيف أي حركات سابقة إن وُجدت
            $this->stockMovementService->clearDocumentMovements($transfer);

            $transfer->loadMissing(['items.product', 'items.productUnit']);

            // 2. التحقق من توفر الأرصدة في المستودع المصدر وتنفيذ الحركات
            foreach ($transfer->items as $item) {
                $this->validateStockAvailability($transfer->from_warehouse_id, $item);

                // حركة الصرف من المستودع المصدر (transfer_out)
                $this->stockMovementService->recordMovement(
                    productId: $item->product_id,
                    warehouseId: $transfer->from_warehouse_id,
                    productUnitId: $item->product_unit_id,
                    movementType: 'transfer_out',
                    quantity: (float) $item->quantity,
                    unitCost: (float) $item->unit_cost,
                    reference: $transfer,
                    locationId: $item->from_location_id,
                    batchId: $item->batch_id,
                    notes: $item->notes ?? $transfer->notes,
                    userId: $userId
                );

                // حركة الإيداع في المستودع الهدف (transfer_in)
                $this->stockMovementService->recordMovement(
                    productId: $item->product_id,
                    warehouseId: $transfer->to_warehouse_id,
                    productUnitId: $item->product_unit_id,
                    movementType: 'transfer_in',
                    quantity: (float) $item->quantity,
                    unitCost: (float) $item->unit_cost,
                    reference: $transfer,
                    locationId: $item->to_location_id,
                    batchId: $item->batch_id,
                    notes: $item->notes ?? $transfer->notes,
                    userId: $userId
                );
            }

            // 3. تحديث حالة أمر التحويل إلى مكتمل
            $transfer->update([
                'status'      => 'completed',
                'approved_by' => $userId,
            ]);

            // 4. إنشاء القيد المحاسبي المالي عند اختلاف حسابات المستودعات
            $this->inventoryAccountingService->createTransferJournalEntry($transfer);

            return $transfer->fresh([
                'fromWarehouse',
                'toWarehouse',
                'items.product',
                'items.productUnit',
                'items.fromLocation',
                'items.toLocation',
                'items.batch',
            ]);
        });
    }

    /**
     * إلغاء أمر التحويل (إذا لم يكن مكتملاً)
     */
    public function cancelTransfer(Transfer $transfer): Transfer
    {
        if ($transfer->status === 'completed') {
            throw new InvalidArgumentException('لا يمكن إلغاء أمر تحويل مكتمل ومرحل، يرجى إجراء أمر تحويل عكسي.');
        }

        $transfer->update([
            'status' => 'cancelled',
        ]);

        return $transfer;
    }

    /**
     * حذف أمر التحويل ومسح بنوده (للطلبات غير المكتملة فقط)
     */
    public function deleteTransfer(Transfer $transfer): bool
    {
        if ($transfer->status === 'completed') {
            throw new InvalidArgumentException('لا يمكن حذف أمر تحويل مكتمل.');
        }

        return DB::transaction(function () use ($transfer) {
            $this->stockMovementService->clearDocumentMovements($transfer);
            $transfer->items()->delete();

            return (bool) $transfer->delete();
        });
    }

    /**
     * حفظ وتجهيز بنود أمر التحويل واحتساب التكاليف
     */
    protected function saveTransferItems(Transfer $transfer, array $itemsData): void
    {
        foreach ($itemsData as $item) {
            $quantity = (float) $item['quantity'];
            $unitCost = isset($item['unit_cost']) && (float) $item['unit_cost'] > 0
                ? (float) $item['unit_cost']
                : 0.0;

            // جلب التكلفة الافتراضية من بطاقة المنتج إذا لم تُمرر التكلفة
            if ($unitCost <= 0.0) {
                $product = Product::find($item['product_id']);
                $productUnit = ProductUnit::find($item['product_unit_id']);
                $conversionFactor = $productUnit && (float) $productUnit->conversion_factor > 0
                    ? (float) $productUnit->conversion_factor
                    : 1.0;

                if ($product && (float) $product->cost_price > 0) {
                    $unitCost = round((float) $product->cost_price * $conversionFactor, 4);
                }
            }

            $totalCost = round($quantity * $unitCost, 4);

            TransferItem::create([
                'transfer_id'      => $transfer->id,
                'product_id'       => $item['product_id'],
                'product_unit_id'  => $item['product_unit_id'],
                'from_location_id' => $item['from_location_id'] ?? null,
                'to_location_id'   => $item['to_location_id'] ?? null,
                'batch_id'         => $item['batch_id'] ?? null,
                'quantity'         => $quantity,
                'unit_cost'        => $unitCost,
                'total_cost'       => $totalCost,
                'notes'            => $item['notes'] ?? null,
            ]);
        }
    }

    /**
     * التحقق من كفاية الرصيد في المستودع المصدر
     */
    protected function validateStockAvailability(int $warehouseId, TransferItem $item): void
    {
        $productUnit = ProductUnit::where('id', $item->product_unit_id)
            ->where('product_id', $item->product_id)
            ->first();

        $conversionFactor = $productUnit && (float) $productUnit->conversion_factor > 0
            ? (float) $productUnit->conversion_factor
            : 1.0;

        $requiredBaseQuantity = (float) $item->quantity * $conversionFactor;

        $stockQuery = ProductStock::where('warehouse_id', $warehouseId)
            ->where('product_id', $item->product_id)
            ->where('batch_id', $item->batch_id);

        if ($item->from_location_id !== null) {
            $stockQuery->where('location_id', $item->from_location_id);
        }

        $availableStock = (float) $stockQuery->sum('quantity');

        if ($availableStock < $requiredBaseQuantity) {
            $productName = $item->product?->name ?? (string) $item->product_id;
            throw new InvalidArgumentException("الرصيد غير كافٍ للمنتج ({$productName}) في المستودع المصدر لإتمام عملية التحويل.");
        }
    }

    /**
     * توليد رقم تسلسلي معتمد لأمر التحويل بصيغة TR-{YM}-{0000}
     */
    protected function generateTransferNumber(): string
    {
        $now = Carbon::now();
        $modelKey = 'inv_transfer';

        $sequence = DB::table('sequences')
            ->where('model', $modelKey)
            ->whereNull('branch_id')
            ->lockForUpdate()
            ->first();

        if (!$sequence) {
            // صيغة احتياطية في حال عدم وجود سجل التسلسل
            $yearMonth = $now->format('ym');
            $latestId = (int) (Transfer::withTrashed()->max('id') ?? 0) + 1;
            return sprintf('TR-%s-%04d', $yearMonth, $latestId);
        }

        $currentYear = (int) $sequence->current_year;
        $currentMonth = (int) $sequence->current_month;
        $nextVal = (int) $sequence->next_value;

        // إعادة التصفير إذا كان التردد شهرياً وتغير الشهر أو السنة
        if ($sequence->reset_frequency === 'monthly' && ($currentYear !== $now->year || $currentMonth !== $now->month)) {
            $nextVal = 1;
            $currentYear = $now->year;
            $currentMonth = $now->month;
        }

        $formattedNumber = str_replace(
            ['{YM}', '{Y}', '{0000}'],
            [$now->format('ym'), (string) $now->year, sprintf('%04d', $nextVal)],
            $sequence->format
        );

        DB::table('sequences')
            ->where('id', $sequence->id)
            ->update([
                'next_value'    => $nextVal + 1,
                'current_year'  => $currentYear,
                'current_month' => $currentMonth,
                'updated_at'    => $now,
            ]);

        return $formattedNumber;
    }
}