<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services\Reports;

use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Models\ReorderRule;
use Illuminate\Database\Eloquent\Builder;

class ReorderAlertReportService
{
    /**
     * تقرير نواقص المخزون وحدود إعادة الطلب
     */
    public function generate(array $filters): array
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
}