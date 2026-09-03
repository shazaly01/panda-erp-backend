<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services\Reports;

use App\Modules\Inventory\Models\StockSerial;
use Illuminate\Database\Eloquent\Builder;

class SerialTrackingReportService
{
    /**
     * تقرير تتبع الأرقام التسلسلية ومطابقتها بمواقعها وحالاتها
     */
    public function generate(array $filters): array
    {
        $productId = isset($filters['product_id']) ? (int) $filters['product_id'] : null;
        $warehouseId = isset($filters['warehouse_id']) ? (int) $filters['warehouse_id'] : null;
        $status = $filters['status'] ?? null;
        $search = $filters['search'] ?? null;

        $serials = StockSerial::query()
            ->with([
                'product',
                'warehouse',
                'location',
                'batch',
                'movements',
            ])
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
                'location' => $serial->location ? [
                    'id' => $serial->location->id,
                    'name' => $serial->location->name,
                ] : null,
                'batch' => $serial->batch ? [
                    'id' => $serial->batch->id,
                    'batch_number' => $serial->batch->batch_number,
                ] : null,
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
}