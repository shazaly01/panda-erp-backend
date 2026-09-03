<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services\Reports;

use App\Modules\Inventory\Models\ProductionOrder;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class ProductionVarianceReportService
{
    /**
     * تقرير انحرافات واستهلاك وتكاليف أوامر الإنتاج
     */
    public function generate(array $filters): array
    {
        $status = $filters['status'] ?? null;
        $productId = isset($filters['product_id']) ? (int) $filters['product_id'] : null;
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
            ->when($productId !== null, fn (Builder $q) => $q->where('product_id', $productId))
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