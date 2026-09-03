<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\Adjustment;
use App\Modules\Inventory\Models\AdjustmentItem;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Models\ProductionOrder;
use App\Modules\Inventory\Models\ReorderRule;
use App\Modules\Inventory\Models\StockBatch;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockSerial;
use App\Modules\Inventory\Models\Transfer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class InventoryReportService
{
    /**
     * أنواع الحركات المصنفة كوارد (إضافة)
     */
    protected const IN_MOVEMENT_TYPES = [
        'in',
        'transfer_in',
        'adjustment_in',
        'production_in',
    ];

    /**
     * أنواع الحركات المصنفة كمنصرف (خصم)
     */
    protected const OUT_MOVEMENT_TYPES = [
        'out',
        'transfer_out',
        'adjustment_out',
        'production_out',
    ];

    /**
     * 1. تقرير كارت الصنف التفصيلي (Item Stock Card)
     */
    public function getItemStockCard(array $filters): array
    {
        $productId = (int) ($filters['product_id'] ?? 0);
        $warehouseId = isset($filters['warehouse_id']) ? (int) $filters['warehouse_id'] : null;
        $startDate = !empty($filters['start_date']) ? Carbon::parse($filters['start_date'])->startOfDay() : null;
        $endDate = !empty($filters['end_date']) ? Carbon::parse($filters['end_date'])->endOfDay() : null;

        $product = Product::with(['category'])->findOrFail($productId);

        // حساب الرصيد الافتتاحي قبل تاريخ البدء
        $openingBalance = 0.0000;
        $openingCost = 0.0000;

        if ($startDate !== null) {
            $priorMovements = StockMovement::query()
                ->where('product_id', $productId)
                ->where('created_at', '<', $startDate)
                ->when($warehouseId !== null, fn (Builder $q) => $q->where('warehouse_id', $warehouseId))
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            foreach ($priorMovements as $mv) {
                $qty = (float) $mv->quantity;
                $cost = (float) $mv->total_cost;

                if (in_array($mv->movement_type, self::IN_MOVEMENT_TYPES, true)) {
                    $openingBalance += $qty;
                    $openingCost += $cost;
                } elseif (in_array($mv->movement_type, self::OUT_MOVEMENT_TYPES, true)) {
                    $openingBalance -= $qty;
                    $openingCost -= $cost;
                }
            }
        }

        // استعلام الحركات خلال الفترة المحددة
        $movementsQuery = StockMovement::query()
            ->with([
                'warehouse',
                'location',
                'productUnit.unit',
                'batch',
                'serial',
                'creator',
                'reference',
            ])
            ->where('product_id', $productId)
            ->when($warehouseId !== null, fn (Builder $q) => $q->where('warehouse_id', $warehouseId))
            ->when($startDate !== null, fn (Builder $q) => $q->where('created_at', '>=', $startDate))
            ->when($endDate !== null, fn (Builder $q) => $q->where('created_at', '<=', $endDate))
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc');

        $movements = $movementsQuery->get();

        $runningBalance = $openingBalance;
        $totalInQty = 0.0000;
        $totalOutQty = 0.0000;
        $totalInCost = 0.0000;
        $totalOutCost = 0.0000;

        $detailedMovements = [];

        foreach ($movements as $movement) {
            $qty = (float) $movement->quantity;
            $unitCost = (float) $movement->unit_cost;
            $totalCost = (float) $movement->total_cost;
            $inQty = 0.0000;
            $outQty = 0.0000;

            if (in_array($movement->movement_type, self::IN_MOVEMENT_TYPES, true)) {
                $inQty = $qty;
                $runningBalance += $qty;
                $totalInQty += $qty;
                $totalInCost += $totalCost;
            } elseif (in_array($movement->movement_type, self::OUT_MOVEMENT_TYPES, true)) {
                $outQty = $qty;
                $runningBalance -= $qty;
                $totalOutQty += $qty;
                $totalOutCost += $totalCost;
            }

            $referenceDetails = null;
            if ($movement->reference) {
                $referenceDetails = [
                    'type' => class_basename($movement->reference_type),
                    'id' => $movement->reference_id,
                    'document_number' => $movement->reference->transfer_number
                        ?? $movement->reference->adjustment_number
                        ?? $movement->reference->order_number
                        ?? (string) $movement->reference_id,
                ];
            }

            $detailedMovements[] = [
                'id' => $movement->id,
                'created_at' => $movement->created_at->format('Y-m-d H:i:s'),
                'movement_type' => $movement->movement_type,
                'warehouse' => [
                    'id' => $movement->warehouse?->id,
                    'name' => $movement->warehouse?->name,
                    'code' => $movement->warehouse?->code,
                ],
                'location' => $movement->location ? [
                    'id' => $movement->location->id,
                    'name' => $movement->location->name,
                    'code' => $movement->location->code,
                ] : null,
                'unit' => $movement->productUnit?->unit?->name,
                'batch_number' => $movement->batch?->batch_number,
                'serial_number' => $movement->serial?->serial_number,
                'in_quantity' => $inQty,
                'out_quantity' => $outQty,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'running_balance' => $runningBalance,
                'recorded_balance_after' => (float) $movement->balance_after_movement,
                'is_balance_aligned' => abs($runningBalance - (float) $movement->balance_after_movement) < 0.0001,
                'notes' => $movement->notes,
                'created_by' => $movement->creator?->name,
                'reference' => $referenceDetails,
            ];
        }

        return [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'category' => $product->category?->name,
                'valuation_method' => $product->valuation_method,
                'current_cost_price' => (float) $product->cost_price,
            ],
            'filters' => [
                'warehouse_id' => $warehouseId,
                'start_date' => $startDate?->format('Y-m-d'),
                'end_date' => $endDate?->format('Y-m-d'),
            ],
            'summary' => [
                'opening_balance' => $openingBalance,
                'opening_cost' => $openingCost,
                'total_in_quantity' => $totalInQty,
                'total_out_quantity' => $totalOutQty,
                'total_in_cost' => $totalInCost,
                'total_out_cost' => $totalOutCost,
                'closing_balance' => $runningBalance,
                'closing_cost' => $openingCost + $totalInCost - $totalOutCost,
            ],
            'movements' => $detailedMovements,
        ];
    }

    /**
     * 2. تقرير أرصدة وتقييم المخزون الحالي (Stock Balance & Valuation)
     */
    public function getStockBalance(array $filters): array
    {
        $warehouseId = isset($filters['warehouse_id']) ? (int) $filters['warehouse_id'] : null;
        $categoryId = isset($filters['category_id']) ? (int) $filters['category_id'] : null;
        $search = $filters['search'] ?? null;
        $onlyInStock = filter_var($filters['only_in_stock'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $query = ProductStock::query()
            ->with([
                'product.category',
                'warehouse',
                'location',
                'batch',
            ])
            ->when($warehouseId !== null, fn (Builder $q) => $q->where('warehouse_id', $warehouseId))
            ->when($categoryId !== null, function (Builder $q) use ($categoryId) {
                $q->whereHas('product', fn (Builder $pq) => $pq->where('category_id', $categoryId));
            })
            ->when($search !== null, function (Builder $q) use ($search) {
                $q->whereHas('product', function (Builder $pq) use ($search) {
                    $pq->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->when($onlyInStock, fn (Builder $q) => $q->where('quantity', '>', 0));

        $stocks = $query->get();

        $totalValuation = 0.0000;
        $totalQuantity = 0.0000;
        $totalReserved = 0.0000;
        $totalAvailable = 0.0000;

        $items = [];

        foreach ($stocks as $stock) {
            $qty = (float) $stock->quantity;
            $reserved = (float) $stock->reserved_quantity;
            $available = $qty - $reserved;
            $costPrice = (float) ($stock->product?->cost_price ?? 0.0000);
            $valuation = $qty * $costPrice;

            $totalQuantity += $qty;
            $totalReserved += $reserved;
            $totalAvailable += $available;
            $totalValuation += $valuation;

            $items[] = [
                'stock_id' => $stock->id,
                'product' => [
                    'id' => $stock->product?->id,
                    'name' => $stock->product?->name,
                    'sku' => $stock->product?->sku,
                    'category' => $stock->product?->category?->name,
                    'valuation_method' => $stock->product?->valuation_method,
                    'cost_price' => $costPrice,
                ],
                'warehouse' => [
                    'id' => $stock->warehouse?->id,
                    'name' => $stock->warehouse?->name,
                    'code' => $stock->warehouse?->code,
                ],
                'location' => $stock->location ? [
                    'id' => $stock->location->id,
                    'name' => $stock->location->name,
                    'code' => $stock->location->code,
                ] : null,
                'batch' => $stock->batch ? [
                    'id' => $stock->batch->id,
                    'batch_number' => $stock->batch->batch_number,
                    'expiry_date' => $stock->batch->expiry_date?->format('Y-m-d'),
                ] : null,
                'quantity' => $qty,
                'reserved_quantity' => $reserved,
                'available_quantity' => $available,
                'total_valuation' => $valuation,
                'has_negative_stock' => $qty < 0,
            ];
        }

        return [
            'summary' => [
                'total_items_count' => count($items),
                'total_quantity' => $totalQuantity,
                'total_reserved' => $totalReserved,
                'total_available' => $totalAvailable,
                'total_valuation' => $totalValuation,
            ],
            'items' => $items,
        ];
    }

    /**
     * 3. تقرير تدقيق المطابقة وسلامة البيانات (Inventory Integrity & Audit Report)
     * يفحص الفروقات بين ProductStock ومجموع StockMovement ويكشف الأرصدة السالبة والانقطاعات
     */
    public function getInventoryIntegrityAudit(array $filters): array
    {
        $warehouseId = isset($filters['warehouse_id']) ? (int) $filters['warehouse_id'] : null;

        // 1. حساب مجموع الحركات الفعلية لكل توليفة (product, warehouse, batch, location)
        $inTypes = implode("','", self::IN_MOVEMENT_TYPES);
        $outTypes = implode("','", self::OUT_MOVEMENT_TYPES);

        $movementAggregates = DB::table('inventory_stock_movements')
            ->whereNull('deleted_at')
            ->when($warehouseId !== null, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->select([
                'product_id',
                'warehouse_id',
                'location_id',
                'batch_id',
                DB::raw("SUM(CASE WHEN movement_type IN ('{$inTypes}') THEN quantity ELSE 0 END) as total_in"),
                DB::raw("SUM(CASE WHEN movement_type IN ('{$outTypes}') THEN quantity ELSE 0 END) as total_out"),
                DB::raw("SUM(CASE WHEN movement_type IN ('{$inTypes}') THEN quantity WHEN movement_type IN ('{$outTypes}') THEN -quantity ELSE 0 END) as calculated_balance"),
            ])
            ->groupBy(['product_id', 'warehouse_id', 'location_id', 'batch_id'])
            ->get();

        // 2. جلب الأرصدة الحالية من جدول ProductStock
        $stocks = ProductStock::query()
            ->with(['product', 'warehouse', 'location', 'batch'])
            ->when($warehouseId !== null, fn (Builder $q) => $q->where('warehouse_id', $warehouseId))
            ->get();

        $auditDiscrepancies = [];
        $negativeStocks = [];
        $matchedCount = 0;

        // إنشاء خريطة للأرصدة الحالية
        $stockMap = [];
        foreach ($stocks as $stock) {
            $key = sprintf(
                '%d-%d-%s-%s',
                $stock->product_id,
                $stock->warehouse_id,
                $stock->location_id ?? 'null',
                $stock->batch_id ?? 'null'
            );
            $stockMap[$key] = $stock;

            if ((float) $stock->quantity < 0) {
                $negativeStocks[] = [
                    'product_id' => $stock->product_id,
                    'product_name' => $stock->product?->name,
                    'sku' => $stock->product?->sku,
                    'warehouse_name' => $stock->warehouse?->name,
                    'quantity' => (float) $stock->quantity,
                ];
            }
        }

        // مقارنة مجموع الحركات مع رصيد ProductStock
        foreach ($movementAggregates as $mvAgg) {
            $key = sprintf(
                '%d-%d-%s-%s',
                $mvAgg->product_id,
                $mvAgg->warehouse_id,
                $mvAgg->location_id ?? 'null',
                $mvAgg->batch_id ?? 'null'
            );

            $currentStock = $stockMap[$key] ?? null;
            $currentQty = $currentStock ? (float) $currentStock->quantity : 0.0000;
            $calculatedQty = (float) $mvAgg->calculated_balance;
            $diff = $currentQty - $calculatedQty;

            if (abs($diff) >= 0.0001) {
                $product = Product::find($mvAgg->product_id);
                $auditDiscrepancies[] = [
                    'product_id' => $mvAgg->product_id,
                    'product_name' => $product?->name,
                    'sku' => $product?->sku,
                    'warehouse_id' => $mvAgg->warehouse_id,
                    'location_id' => $mvAgg->location_id,
                    'batch_id' => $mvAgg->batch_id,
                    'stock_table_quantity' => $currentQty,
                    'movements_calculated_quantity' => $calculatedQty,
                    'difference' => $diff,
                    'severity' => 'CRITICAL',
                ];
            } else {
                $matchedCount++;
            }

            unset($stockMap[$key]);
        }

        // السجلات الموجودة في ProductStock بدون أي حركات
        foreach ($stockMap as $remainingStock) {
            $currentQty = (float) $remainingStock->quantity;
            if (abs($currentQty) >= 0.0001) {
                $auditDiscrepancies[] = [
                    'product_id' => $remainingStock->product_id,
                    'product_name' => $remainingStock->product?->name,
                    'sku' => $remainingStock->product?->sku,
                    'warehouse_id' => $remainingStock->warehouse_id,
                    'location_id' => $remainingStock->location_id,
                    'batch_id' => $remainingStock->batch_id,
                    'stock_table_quantity' => $currentQty,
                    'movements_calculated_quantity' => 0.0000,
                    'difference' => $currentQty,
                    'severity' => 'WARNING_ORPHAN_STOCK',
                ];
            }
        }

        $isSystemHealthy = empty($auditDiscrepancies) && empty($negativeStocks);

        return [
            'audit_status' => $isSystemHealthy ? 'HEALTHY' : 'DISCREPANCIES_FOUND',
            'summary' => [
                'is_system_healthy' => $isSystemHealthy,
                'total_matched_positions' => $matchedCount,
                'discrepancy_count' => count($auditDiscrepancies),
                'negative_stock_count' => count($negativeStocks),
            ],
            'discrepancies' => $auditDiscrepancies,
            'negative_stocks' => $negativeStocks,
        ];
    }

    /**
     * 4. تقرير تسويات وفروقات الجرد (Stock Adjustments & Variance)
     */
    public function getStockDiscrepancyReport(array $filters): array
    {
        $warehouseId = isset($filters['warehouse_id']) ? (int) $filters['warehouse_id'] : null;
        $status = $filters['status'] ?? null;
        $type = $filters['type'] ?? null;
        $startDate = !empty($filters['start_date']) ? Carbon::parse($filters['start_date'])->startOfDay() : null;
        $endDate = !empty($filters['end_date']) ? Carbon::parse($filters['end_date'])->endOfDay() : null;

        $query = Adjustment::query()
            ->with([
                'warehouse',
                'creator',
                'approver',
                'items.product',
                'items.productUnit.unit',
                'items.batch',
                'journalEntry',
            ])
            ->when($warehouseId !== null, fn (Builder $q) => $q->where('warehouse_id', $warehouseId))
            ->when($status !== null, fn (Builder $q) => $q->where('status', $status))
            ->when($type !== null, fn (Builder $q) => $q->where('type', $type))
            ->when($startDate !== null, fn (Builder $q) => $q->where('adjustment_date', '>=', $startDate))
            ->when($endDate !== null, fn (Builder $q) => $q->where('adjustment_date', '<=', $endDate))
            ->orderBy('adjustment_date', 'desc')
            ->orderBy('id', 'desc');

        $adjustments = $query->get();

        $totalSurplusCost = 0.0000;
        $totalDeficitCost = 0.0000;

        $results = [];

        foreach ($adjustments as $adj) {
            $itemsList = [];
            $adjSurplus = 0.0000;
            $adjDeficit = 0.0000;

            foreach ($adj->items as $item) {
                $diff = (float) $item->quantity_difference;
                $totCost = (float) $item->total_cost;

                if ($diff > 0) {
                    $adjSurplus += $totCost;
                } else {
                    $adjDeficit += abs($totCost);
                }

                $itemsList[] = [
                    'id' => $item->id,
                    'product_name' => $item->product?->name,
                    'sku' => $item->product?->sku,
                    'unit' => $item->productUnit?->unit?->name,
                    'batch_number' => $item->batch?->batch_number,
                    'current_quantity' => (float) $item->current_quantity,
                    'actual_quantity' => (float) $item->actual_quantity,
                    'quantity_difference' => $diff,
                    'unit_cost' => (float) $item->unit_cost,
                    'total_cost' => $totCost,
                    'variance_type' => $diff >= 0 ? 'SURPLUS' : 'DEFICIT',
                    'notes' => $item->notes,
                ];
            }

            $totalSurplusCost += $adjSurplus;
            $totalDeficitCost += $adjDeficit;

            $results[] = [
                'id' => $adj->id,
                'adjustment_number' => $adj->adjustment_number,
                'adjustment_date' => $adj->adjustment_date?->format('Y-m-d'),
                'type' => $adj->type,
                'status' => $adj->status,
                'warehouse' => [
                    'id' => $adj->warehouse?->id,
                    'name' => $adj->warehouse?->name,
                ],
                'created_by' => $adj->creator?->name,
                'approved_by' => $adj->approver?->name,
                'total_cost' => (float) $adj->total_cost,
                'surplus_cost' => $adjSurplus,
                'deficit_cost' => $adjDeficit,
                'has_journal_entry' => $adj->journalEntry !== null,
                'journal_entry_id' => $adj->journalEntry?->id,
                'items_count' => count($itemsList),
                'items' => $itemsList,
            ];
        }

        return [
            'summary' => [
                'total_adjustments_count' => count($results),
                'total_surplus_cost' => $totalSurplusCost,
                'total_deficit_cost' => $totalDeficitCost,
                'net_variance_cost' => $totalSurplusCost - $totalDeficitCost,
            ],
            'adjustments' => $results,
        ];
    }

    /**
     * 5. تقرير متابعة التحويلات المخزنية (Stock Transfers Tracking)
     */
    public function getTransferTrackingReport(array $filters): array
    {
        $fromWarehouseId = isset($filters['from_warehouse_id']) ? (int) $filters['from_warehouse_id'] : null;
        $toWarehouseId = isset($filters['to_warehouse_id']) ? (int) $filters['to_warehouse_id'] : null;
        $status = $filters['status'] ?? null;
        $startDate = !empty($filters['start_date']) ? Carbon::parse($filters['start_date'])->startOfDay() : null;
        $endDate = !empty($filters['end_date']) ? Carbon::parse($filters['end_date'])->endOfDay() : null;

        $query = Transfer::query()
            ->with([
                'fromWarehouse',
                'toWarehouse',
                'creator',
                'approver',
                'items.product',
                'items.productUnit.unit',
                'items.batch',
                'items.fromLocation',
                'items.toLocation',
            ])
            ->when($fromWarehouseId !== null, fn (Builder $q) => $q->where('from_warehouse_id', $fromWarehouseId))
            ->when($toWarehouseId !== null, fn (Builder $q) => $q->where('to_warehouse_id', $toWarehouseId))
            ->when($status !== null, fn (Builder $q) => $q->where('status', $status))
            ->when($startDate !== null, fn (Builder $q) => $q->where('transfer_date', '>=', $startDate))
            ->when($endDate !== null, fn (Builder $q) => $q->where('transfer_date', '<=', $endDate))
            ->orderBy('transfer_date', 'desc')
            ->orderBy('id', 'desc');

        $transfers = $query->get();

        $totalTransferredQty = 0.0000;
        $totalTransferredCost = 0.0000;

        $items = [];

        foreach ($transfers as $tr) {
            $orderItems = [];
            foreach ($tr->items as $ti) {
                $qty = (float) $ti->quantity;
                $cost = (float) $ti->total_cost;
                $totalTransferredQty += $qty;
                $totalTransferredCost += $cost;

                $orderItems[] = [
                    'id' => $ti->id,
                    'product_name' => $ti->product?->name,
                    'sku' => $ti->product?->sku,
                    'unit' => $ti->productUnit?->unit?->name,
                    'batch_number' => $ti->batch?->batch_number,
                    'from_location' => $ti->fromLocation?->name,
                    'to_location' => $ti->toLocation?->name,
                    'quantity' => $qty,
                    'unit_cost' => (float) $ti->unit_cost,
                    'total_cost' => $cost,
                    'notes' => $ti->notes,
                ];
            }

            $items[] = [
                'id' => $tr->id,
                'transfer_number' => $tr->transfer_number,
                'transfer_date' => $tr->transfer_date?->format('Y-m-d'),
                'status' => $tr->status,
                'from_warehouse' => [
                    'id' => $tr->fromWarehouse?->id,
                    'name' => $tr->fromWarehouse?->name,
                ],
                'to_warehouse' => [
                    'id' => $tr->toWarehouse?->id,
                    'name' => $tr->toWarehouse?->name,
                ],
                'created_by' => $tr->creator?->name,
                'approved_by' => $tr->approver?->name,
                'items_count' => count($orderItems),
                'items' => $orderItems,
                'notes' => $tr->notes,
            ];
        }

        return [
            'summary' => [
                'total_transfers_count' => count($items),
                'total_transferred_quantity' => $totalTransferredQty,
                'total_transferred_cost' => $totalTransferredCost,
            ],
            'transfers' => $items,
        ];
    }

    /**
     * 6. تقرير صلاحيات وتتبع التشغيلات (Batches & Expiry Report)
     */
    public function getBatchExpiryReport(array $filters): array
    {
        $productId = isset($filters['product_id']) ? (int) $filters['product_id'] : null;
        $warehouseId = isset($filters['warehouse_id']) ? (int) $filters['warehouse_id'] : null;
        $daysThreshold = (int) ($filters['days_threshold'] ?? 60);

        $now = Carbon::now();
        $targetDate = $now->copy()->addDays($daysThreshold);

        $batches = StockBatch::query()
            ->with(['product', 'stocks.warehouse', 'stocks.location'])
            ->when($productId !== null, fn (Builder $q) => $q->where('product_id', $productId))
            ->when($warehouseId !== null, function (Builder $q) use ($warehouseId) {
                $q->whereHas('stocks', fn (Builder $sq) => $sq->where('warehouse_id', $warehouseId));
            })
            ->orderBy('expiry_date', 'asc')
            ->get();

        $expiredBatches = [];
        $nearExpiryBatches = [];
        $validBatches = [];

        foreach ($batches as $batch) {
            $totalStock = (float) $batch->stocks->sum('quantity');
            if ($totalStock <= 0) {
                continue;
            }

            $expiryDate = $batch->expiry_date ? Carbon::parse($batch->expiry_date) : null;
            $daysRemaining = $expiryDate ? (int) $now->diffInDays($expiryDate, false) : null;

            $batchData = [
                'id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'product' => [
                    'id' => $batch->product?->id,
                    'name' => $batch->product?->name,
                    'sku' => $batch->product?->sku,
                ],
                'manufacturing_date' => $batch->manufacturing_date?->format('Y-m-d'),
                'expiry_date' => $batch->expiry_date?->format('Y-m-d'),
                'days_remaining' => $daysRemaining,
                'current_stock' => $totalStock,
                'is_active' => $batch->is_active,
                'warehouses_breakdown' => $batch->stocks->map(fn ($s) => [
                    'warehouse' => $s->warehouse?->name,
                    'location' => $s->location?->name,
                    'quantity' => (float) $s->quantity,
                ])->values()->all(),
            ];

            if ($daysRemaining !== null && $daysRemaining < 0) {
                $batchData['status'] = 'EXPIRED';
                $expiredBatches[] = $batchData;
            } elseif ($daysRemaining !== null && $daysRemaining <= $daysThreshold) {
                $batchData['status'] = 'NEAR_EXPIRY';
                $nearExpiryBatches[] = $batchData;
            } else {
                $batchData['status'] = 'VALID';
                $validBatches[] = $batchData;
            }
        }

        return [
            'summary' => [
                'days_threshold' => $daysThreshold,
                'expired_count' => count($expiredBatches),
                'near_expiry_count' => count($nearExpiryBatches),
                'valid_count' => count($validBatches),
            ],
            'expired' => $expiredBatches,
            'near_expiry' => $nearExpiryBatches,
            'valid' => $validBatches,
        ];
    }

    /**
     * 7. تقرير تتبع الأرقام التسلسلية (Serial Numbers Tracking)
     */
    public function getSerialTrackingReport(array $filters): array
    {
        $productId = isset($filters['product_id']) ? (int) $filters['product_id'] : null;
        $warehouseId = isset($filters['warehouse_id']) ? (int) $filters['warehouse_id'] : null;
        $status = $filters['status'] ?? null;
        $search = $filters['search'] ?? null;

        $serials = StockSerial::query()
            ->with(['product', 'warehouse', 'location', 'batch', 'movements'])
            ->when($productId !== null, fn (Builder $q) => $q->where('product_id', $productId))
            ->when($warehouseId !== null, fn (Builder $q) => $q->where('warehouse_id', $warehouseId))
            ->when($status !== null, fn (Builder $q) => $q->where('status', $status))
            ->when($search !== null, fn (Builder $q) => $q->where('serial_number', 'like', "%{$search}%"))
            ->orderBy('id', 'desc')
            ->get();

        $items = [];

        foreach ($serials as $serial) {
            $items[] = [
                'id' => $serial->id,
                'serial_number' => $serial->serial_number,
                'status' => $serial->status,
                'product' => [
                    'id' => $serial->product?->id,
                    'name' => $serial->product?->name,
                    'sku' => $serial->product?->sku,
                ],
                'warehouse' => [
                    'id' => $serial->warehouse?->id,
                    'name' => $serial->warehouse?->name,
                ],
                'location' => $serial->location?->name,
                'batch_number' => $serial->batch?->batch_number,
                'movements_count' => $serial->movements->count(),
                'notes' => $serial->notes,
            ];
        }

        return [
            'summary' => [
                'total_serials_count' => count($items),
            ],
            'serials' => $items,
        ];
    }

    /**
     * 8. تقرير نواقص وحدود إعادة الطلب (Reorder Alert Report)
     */
    public function getReorderAlertReport(array $filters): array
    {
        $warehouseId = isset($filters['warehouse_id']) ? (int) $filters['warehouse_id'] : null;

        $rules = ReorderRule::query()
            ->with(['product', 'warehouse'])
            ->where('is_active', true)
            ->when($warehouseId !== null, fn (Builder $q) => $q->where('warehouse_id', $warehouseId))
            ->get();

        $alerts = [];

        foreach ($rules as $rule) {
            $totalStock = (float) ProductStock::query()
                ->where('product_id', $rule->product_id)
                ->where('warehouse_id', $rule->warehouse_id)
                ->sum('quantity');

            $reservedStock = (float) ProductStock::query()
                ->where('product_id', $rule->product_id)
                ->where('warehouse_id', $rule->warehouse_id)
                ->sum('reserved_quantity');

            $availableStock = $totalStock - $reservedStock;
            $minQty = (float) $rule->min_quantity;
            $reorderQty = (float) $rule->reorder_quantity;

            if ($availableStock <= $minQty) {
                $deficit = $minQty - $availableStock;
                $alerts[] = [
                    'product_id' => $rule->product_id,
                    'product_name' => $rule->product?->name,
                    'sku' => $rule->product?->sku,
                    'warehouse' => [
                        'id' => $rule->warehouse?->id,
                        'name' => $rule->warehouse?->name,
                    ],
                    'available_stock' => $availableStock,
                    'min_quantity' => $minQty,
                    'max_quantity' => (float) $rule->max_quantity,
                    'reorder_quantity' => $reorderQty,
                    'shortage_quantity' => $deficit,
                    'suggested_purchase_quantity' => max($reorderQty, $deficit),
                ];
            }
        }

        return [
            'summary' => [
                'total_alerts_count' => count($alerts),
            ],
            'alerts' => $alerts,
        ];
    }

    /**
     * 9. تقرير انحرافات وتكاليف أوامر الإنتاج (Production Variance Report)
     */
    public function getProductionVarianceReport(array $filters): array
    {
        $status = $filters['status'] ?? null;
        $startDate = !empty($filters['start_date']) ? Carbon::parse($filters['start_date'])->startOfDay() : null;
        $endDate = !empty($filters['end_date']) ? Carbon::parse($filters['end_date'])->endOfDay() : null;

        $orders = ProductionOrder::query()
            ->with([
                'product',
                'bom',
                'rawMaterialsWarehouse',
                'finishedGoodsWarehouse',
                'producedBatch',
                'creator',
                'approver',
                'items.rawMaterial',
                'items.productUnit.unit',
                'items.batch',
            ])
            ->when($status !== null, fn (Builder $q) => $q->where('status', $status))
            ->when($startDate !== null, fn (Builder $q) => $q->where('production_date', '>=', $startDate))
            ->when($endDate !== null, fn (Builder $q) => $q->where('production_date', '<=', $endDate))
            ->orderBy('production_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $results = [];

        foreach ($orders as $order) {
            $plannedQty = (float) $order->planned_quantity;
            $actualQty = (float) $order->actual_quantity;
            $finishedGoodsVariance = $actualQty - $plannedQty;

            $itemsList = [];
            $totalRawMaterialsCost = 0.0000;

            foreach ($order->items as $item) {
                $rawPlanned = (float) $item->planned_quantity;
                $rawActual = (float) $item->actual_quantity;
                $totCost = (float) $item->total_cost;
                $totalRawMaterialsCost += $totCost;

                $itemsList[] = [
                    'raw_material_id' => $item->raw_material_id,
                    'raw_material_name' => $item->rawMaterial?->name,
                    'sku' => $item->rawMaterial?->sku,
                    'unit' => $item->productUnit?->unit?->name,
                    'batch_number' => $item->batch?->batch_number,
                    'planned_quantity' => $rawPlanned,
                    'actual_quantity' => $rawActual,
                    'quantity_variance' => $rawActual - $rawPlanned,
                    'unit_cost' => (float) $item->unit_cost,
                    'total_cost' => $totCost,
                ];
            }

            $additionalCosts = (float) $order->additional_costs;
            $totalOrderCost = $totalRawMaterialsCost + $additionalCosts;
            $unitProducedCost = $actualQty > 0 ? ($totalOrderCost / $actualQty) : 0.0000;

            $results[] = [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'production_date' => $order->production_date?->format('Y-m-d'),
                'status' => $order->status,
                'product' => [
                    'id' => $order->product?->id,
                    'name' => $order->product?->name,
                    'sku' => $order->product?->sku,
                ],
                'raw_materials_warehouse' => $order->rawMaterialsWarehouse?->name,
                'finished_goods_warehouse' => $order->finishedGoodsWarehouse?->name,
                'produced_batch' => $order->producedBatch?->batch_number,
                'planned_quantity' => $plannedQty,
                'actual_quantity' => $actualQty,
                'quantity_variance' => $finishedGoodsVariance,
                'additional_costs' => $additionalCosts,
                'raw_materials_total_cost' => $totalRawMaterialsCost,
                'total_order_cost' => $totalOrderCost,
                'unit_produced_cost' => $unitProducedCost,
                'created_by' => $order->creator?->name,
                'approved_by' => $order->approver?->name,
                'items' => $itemsList,
            ];
        }

        return [
            'summary' => [
                'total_orders_count' => count($results),
            ],
            'orders' => $results,
        ];
    }
}