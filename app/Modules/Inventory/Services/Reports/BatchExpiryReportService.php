<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services\Reports;

use App\Modules\Inventory\Models\StockBatch;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class BatchExpiryReportService
{
    /**
     * تقرير صلاحيات ودفعات التشغيل والمنتجات الراكدة
     */
    public function generate(array $filters): array
    {
        $productId = isset($filters['product_id']) ? (int) $filters['product_id'] : null;
        $warehouseId = isset($filters['warehouse_id']) ? (int) $filters['warehouse_id'] : null;
        $daysThreshold = (int) ($filters['days_threshold'] ?? 60);

        $now = Carbon::now();

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
}