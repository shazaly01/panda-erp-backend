<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services\Reports;

use App\Modules\Inventory\Models\Transfer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class TransferTrackingReportService
{
    /**
     * تقرير متابعة التحويلات المخزنية ومطابقة الكميات المنقولة
     */
    public function generate(array $filters): array
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
}