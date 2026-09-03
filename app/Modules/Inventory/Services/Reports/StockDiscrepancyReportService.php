<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services\Reports;

use App\Modules\Inventory\Models\Adjustment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class StockDiscrepancyReportService
{
    /**
     * تقرير تسويات وفروقات الجرد الفعلي والدفتري
     */
    public function generate(array $filters): array
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
}