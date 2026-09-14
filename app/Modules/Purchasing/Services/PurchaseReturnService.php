<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Services;

use App\Modules\Inventory\Services\StockMovementService;
use App\Modules\Purchasing\Enums\PurchaseReturnStatus;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Modules\Purchasing\Models\PurchaseReturnItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PurchaseReturnService
{
    public function __construct(
        protected readonly StockMovementService $stockMovementService,
        protected readonly PurchasingAccountingService $accountingService
    ) {}

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = PurchaseReturn::query()
            ->with([
                'supplier',
                'warehouse',
                'currency',
                'bill',
                'poster',
            ])
            ->withCount('items');

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('return_number', 'like', "%{$search}%")
                    ->orWhere('return_reason', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (! empty($filters['currency_id'])) {
            $query->where('currency_id', $filters['currency_id']);
        }

        if (! empty($filters['bill_id'])) {
            $query->where('bill_id', $filters['bill_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('return_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('return_date', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    public function find(int $id): PurchaseReturn
    {
        return PurchaseReturn::with([
            'supplier',
            'warehouse',
            'currency',
            'bill',
            'poster',
            'creator',
            'updater',
            'stockMovements',
            'journalEntries.details.account',
            'items.product',
            'items.productUnit',
            'items.location',
            'items.batch',
            'items.billItem',
            'items.creator',
            'items.updater',
        ])->findOrFail($id);
    }

    public function create(array $data, int $userId): PurchaseReturn
    {
        return DB::transaction(function () use ($data, $userId): PurchaseReturn {
            $returnNumber = $this->generateReturnNumber();
            $calculatedData = $this->calculateReturnFinancials($data['items']);

            $return = PurchaseReturn::create([
                'return_number' => $returnNumber,
                'bill_id' => $data['bill_id'] ?? null,
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => $data['warehouse_id'],
                'currency_id' => $data['currency_id'],
                'exchange_rate' => $data['exchange_rate'] ?? 1.0000,
                'return_date' => $data['return_date'] ?? Carbon::now()->toDateString(),
                'status' => PurchaseReturnStatus::DRAFT,
                'return_reason' => $data['return_reason'] ?? null,
                'subtotal' => $calculatedData['subtotal'],
                'tax_amount' => $calculatedData['tax_amount'],
                'total_amount' => $calculatedData['total_amount'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            foreach ($calculatedData['items'] as $itemData) {
                $return->items()->create([
                    'bill_item_id' => $itemData['bill_item_id'] ?? null,
                    'product_id' => $itemData['product_id'],
                    'product_unit_id' => $itemData['product_unit_id'],
                    'location_id' => $itemData['location_id'] ?? null,
                    'batch_id' => $itemData['batch_id'] ?? null,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'tax_rate' => $itemData['tax_rate'],
                    'tax_amount' => $itemData['tax_amount'],
                    'subtotal' => $itemData['subtotal'],
                    'total' => $itemData['total'],
                    'reason' => $itemData['reason'] ?? null,
                    'notes' => $itemData['notes'] ?? null,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }

            return $this->find($return->id);
        });
    }

    public function update(PurchaseReturn $return, array $data, int $userId): PurchaseReturn
    {
        if ($return->status !== PurchaseReturnStatus::DRAFT) {
            throw new RuntimeException('لا يمكن تعديل سند المردود إلا إذا كان في حالة مسودة.');
        }

        return DB::transaction(function () use ($return, $data, $userId): PurchaseReturn {
            $calculatedData = $this->calculateReturnFinancials($data['items']);

            $return->update([
                'bill_id' => array_key_exists('bill_id', $data) ? $data['bill_id'] : $return->bill_id,
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => $data['warehouse_id'],
                'currency_id' => $data['currency_id'],
                'exchange_rate' => $data['exchange_rate'] ?? $return->exchange_rate,
                'return_date' => $data['return_date'] ?? $return->return_date,
                'return_reason' => array_key_exists('return_reason', $data) ? $data['return_reason'] : $return->return_reason,
                'subtotal' => $calculatedData['subtotal'],
                'tax_amount' => $calculatedData['tax_amount'],
                'total_amount' => $calculatedData['total_amount'],
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $return->notes,
                'updated_by' => $userId,
            ]);

            $existingItems = $return->items()->get()->keyBy('id');
            $submittedItemIds = collect($calculatedData['items'])->pluck('id')->filter()->map(fn ($id) => (int) $id)->toArray();

            foreach ($existingItems as $existingId => $existingItem) {
                if (! in_array($existingId, $submittedItemIds, true)) {
                    $existingItem->delete();
                }
            }

            foreach ($calculatedData['items'] as $itemData) {
                if (! empty($itemData['id']) && $existingItems->has($itemData['id'])) {
                    /** @var PurchaseReturnItem $existingItem */
                    $existingItem = $existingItems->get($itemData['id']);
                    $existingItem->update([
                        'bill_item_id' => $itemData['bill_item_id'] ?? $existingItem->bill_item_id,
                        'product_id' => $itemData['product_id'],
                        'product_unit_id' => $itemData['product_unit_id'],
                        'location_id' => $itemData['location_id'] ?? $existingItem->location_id,
                        'batch_id' => $itemData['batch_id'] ?? $existingItem->batch_id,
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                        'tax_rate' => $itemData['tax_rate'],
                        'tax_amount' => $itemData['tax_amount'],
                        'subtotal' => $itemData['subtotal'],
                        'total' => $itemData['total'],
                        'reason' => $itemData['reason'] ?? null,
                        'notes' => $itemData['notes'] ?? null,
                        'updated_by' => $userId,
                    ]);
                } else {
                    $return->items()->create([
                        'bill_item_id' => $itemData['bill_item_id'] ?? null,
                        'product_id' => $itemData['product_id'],
                        'product_unit_id' => $itemData['product_unit_id'],
                        'location_id' => $itemData['location_id'] ?? null,
                        'batch_id' => $itemData['batch_id'] ?? null,
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                        'tax_rate' => $itemData['tax_rate'],
                        'tax_amount' => $itemData['tax_amount'],
                        'subtotal' => $itemData['subtotal'],
                        'total' => $itemData['total'],
                        'reason' => $itemData['reason'] ?? null,
                        'notes' => $itemData['notes'] ?? null,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);
                }
            }

            return $this->find($return->id);
        });
    }

    public function delete(PurchaseReturn $return): bool
    {
        if ($return->status !== PurchaseReturnStatus::DRAFT) {
            throw new RuntimeException('لا يمكن حذف سند المردود إلا إذا كان في حالة مسودة.');
        }

        return DB::transaction(function () use ($return): bool {
            $return->items()->delete();
            return (bool) $return->delete();
        });
    }

    public function post(PurchaseReturn $return, int $userId): PurchaseReturn
    {
        if ($return->status !== PurchaseReturnStatus::DRAFT) {
            throw new RuntimeException('لا يمكن ترحيل سند المردود إلا إذا كان في حالة مسودة.');
        }

        if ($return->items()->count() === 0) {
            throw new RuntimeException('لا يمكن ترحيل سند مردود لا يحتوي على أي بنود.');
        }

        return DB::transaction(function () use ($return, $userId): PurchaseReturn {
            $return->loadMissing('items');

            // 1. تسجيل خروج البضاعة المرتجعة من المخزون وتخفيض الأرصدة
            foreach ($return->items as $item) {
                $returnQuantity = (float) $item->quantity;

                if ($returnQuantity > 0) {
                    $this->stockMovementService->recordMovement(
                        productId: (int) $item->product_id,
                        warehouseId: (int) $return->warehouse_id,
                        productUnitId: (int) $item->product_unit_id,
                        movementType: 'purchase_return',
                        quantity: $returnQuantity,
                        unitCost: (float) $item->unit_price,
                        reference: $return,
                        locationId: $item->location_id ? (int) $item->location_id : null,
                        batchId: $item->batch_id ? (int) $item->batch_id : null,
                        notes: "إرجاع مشتريات للمورد بموجب السند رقم: {$return->return_number}",
                        userId: $userId
                    );
                }
            }

            // 2. إنشاء وترحيل القيد المحاسبي المالي
            $this->accountingService->createReturnJournalEntry($return);

            // 3. تحديث حالة المردود وبيانات الترحيل
            $return->update([
                'status' => PurchaseReturnStatus::POSTED,
                'posted_by' => $userId,
                'posted_at' => Carbon::now(),
                'updated_by' => $userId,
            ]);

            return $this->find($return->id);
        });
    }

    public function cancel(PurchaseReturn $return, int $userId): PurchaseReturn
    {
        if ($return->status === PurchaseReturnStatus::CANCELLED) {
            throw new RuntimeException('هذا السند ملغي بالفعل.');
        }

        return DB::transaction(function () use ($return, $userId): PurchaseReturn {
            if ($return->status === PurchaseReturnStatus::POSTED) {
                // عكس حركات المخزون وحذف القيد المالي
                $this->stockMovementService->clearDocumentMovements($return);
                $this->accountingService->deleteReturnJournalEntries($return);
            }

            $return->update([
                'status' => PurchaseReturnStatus::CANCELLED,
                'updated_by' => $userId,
            ]);

            return $this->find($return->id);
        });
    }

    protected function calculateReturnFinancials(array $items): array
    {
        $calculatedItems = [];
        $subtotal = 0.0;
        $totalTaxAmount = 0.0;

        foreach ($items as $item) {
            $quantity = (float) $item['quantity'];
            $unitPrice = (float) $item['unit_price'];
            $lineSubtotal = round($quantity * $unitPrice, 4);

            $taxRate = isset($item['tax_rate']) ? (float) $item['tax_rate'] : 0.0;
            $taxAmount = round($lineSubtotal * ($taxRate / 100), 4);
            $lineTotal = round($lineSubtotal + $taxAmount, 4);

            $subtotal += $lineSubtotal;
            $totalTaxAmount += $taxAmount;

            $itemRecord = $item;
            $itemRecord['quantity'] = $quantity;
            $itemRecord['unit_price'] = $unitPrice;
            $itemRecord['tax_rate'] = $taxRate;
            $itemRecord['tax_amount'] = $taxAmount;
            $itemRecord['subtotal'] = $lineSubtotal;
            $itemRecord['total'] = $lineTotal;

            $calculatedItems[] = $itemRecord;
        }

        $totalAmount = round($subtotal + $totalTaxAmount, 4);

        return [
            'items' => $calculatedItems,
            'subtotal' => round($subtotal, 4),
            'tax_amount' => round($totalTaxAmount, 4),
            'total_amount' => max(0.0, $totalAmount),
        ];
    }

    protected function generateReturnNumber(): string
    {
        $prefix = 'RET-' . Carbon::now()->format('Ymd');

        $lastRecord = PurchaseReturn::withTrashed()
            ->where('return_number', 'like', "{$prefix}-%")
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if ($lastRecord && preg_match('/-(\d{4})$/', $lastRecord->return_number, $matches)) {
            $nextSequence = (int) $matches[1] + 1;
        } else {
            $nextSequence = 1;
        }

        return sprintf('%s-%04d', $prefix, $nextSequence);
    }
}