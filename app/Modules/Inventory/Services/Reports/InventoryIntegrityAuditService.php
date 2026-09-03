<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services\Reports;

use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\ProductStock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class InventoryIntegrityAuditService
{
    protected const IN_MOVEMENT_TYPES = [
        'in',
        'transfer_in',
        'adjustment_in',
        'production_in',
    ];

    protected const OUT_MOVEMENT_TYPES = [
        'out',
        'transfer_out',
        'adjustment_out',
        'production_out',
    ];

    /**
     * تقرير فحص المطابقة والتدقيق الرقابي وسلامة البيانات المخزنية
     */
    public function generate(array $filters): array
    {
        $warehouseId = isset($filters['warehouse_id']) ? (int) $filters['warehouse_id'] : null;

        $inTypes = implode("','", self::IN_MOVEMENT_TYPES);
        $outTypes = implode("','", self::OUT_MOVEMENT_TYPES);

        // 1. تجميع الحركات التاريخية الفعلية
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

        // 2. جلب الأرصدة الحالية اللحظية المسجلة في جدول ProductStock
        $stocks = ProductStock::query()
            ->with(['product', 'warehouse', 'location', 'batch'])
            ->when($warehouseId !== null, fn (Builder $q) => $q->where('warehouse_id', $warehouseId))
            ->get();

        $auditDiscrepancies = [];
        $negativeStocks = [];
        $matchedCount = 0;

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
                    'location_name' => $stock->location?->name,
                    'batch_number' => $stock->batch?->batch_number,
                    'quantity' => (float) $stock->quantity,
                ];
            }
        }

        // 3. مقارنة مجموع الحركات بالأرصدة اللحظية
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

        // 4. فحص الأرصدة المعلقة أو الأرصدة بدون حركات (Orphan Stocks)
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
}