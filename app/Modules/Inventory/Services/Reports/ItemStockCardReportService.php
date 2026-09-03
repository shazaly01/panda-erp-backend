<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services\Reports;

use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class ItemStockCardReportService
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
     * توليد تقرير كارت الصنف التفصيلي والرصيد التراكمي
     */
    public function generate(array $filters): array
    {
        $productId = (int) ($filters['product_id'] ?? 0);
        $warehouseId = isset($filters['warehouse_id']) ? (int) $filters['warehouse_id'] : null;
        $startDate = !empty($filters['start_date']) ? Carbon::parse($filters['start_date'])->startOfDay() : null;
        $endDate = !empty($filters['end_date']) ? Carbon::parse($filters['end_date'])->endOfDay() : null;

        $product = Product::with(['category'])->findOrFail($productId);

        // 1. حساب الرصيد الافتتاحي قبل تاريخ البدء المحدد
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

        // 2. جلب حركات الصنف خلال الفترة
        $movements = StockMovement::query()
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
            ->orderBy('id', 'asc')
            ->get();

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
}