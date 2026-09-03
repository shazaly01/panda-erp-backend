<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services\Reports;

use App\Modules\Inventory\Models\ProductStock;
use Illuminate\Database\Eloquent\Builder;

class StockBalanceReportService
{
    /**
     * توليد تقرير أرصدة وتقييم المخزون اللحظي
     */
    public function generate(array $filters): array
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
}