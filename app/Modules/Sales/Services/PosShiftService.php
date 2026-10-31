<?php

declare(strict_types=1);

namespace App\Modules\Sales\Services;

use App\Modules\Core\Services\SequenceService;
use App\Modules\Sales\Models\PosShift;
use App\Modules\Sales\Models\SalesInvoicePayment;
use App\Modules\Sales\Models\SalesReturnPayment;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PosShiftService
{
    public function __construct(
        protected SequenceService $sequenceService
    ) {}

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = PosShift::query()
            ->with([
                'user',
                'warehouse',
                'closedBy',
            ])
            ->withCount([
                'invoices',
                'payments',
                'returns',
                'returnPayments',
            ]);

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('shift_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('user', function (Builder $userQuery) use ($search): void {
                        $userQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('opened_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('opened_at', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    public function find(int $id): PosShift
    {
        return PosShift::with([
            'user',
            'warehouse',
            'closedBy',
            'invoices.partner',
            'payments',
            'returns.partner',
            'returnPayments',
        ])->findOrFail($id);
    }

    public function getCurrentOpenShift(int $userId, ?int $branchId = null): ?PosShift
    {
        $query = PosShift::query()
            ->where('user_id', $userId)
            ->where('status', 'open');

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        return $query->latest('opened_at')->first();
    }

    public function open(array $data, int $userId): PosShift
    {
        return DB::transaction(function () use ($data, $userId): PosShift {
            $hasOpenShift = PosShift::where('user_id', $userId)
                ->where('status', 'open')
                ->lockForUpdate()
                ->exists();

            if ($hasOpenShift) {
                throw new RuntimeException('لديك بالفعل وردية مفتوحة حالياً، يجب إغلاقها أولاً قبل فتح وردية جديدة.');
            }

            $branchId = isset($data['branch_id']) ? (int) $data['branch_id'] : null;
            $openedAt = Carbon::now();

            $shiftNumber = $this->sequenceService->generateNumber(
                'pos_shift',
                $branchId,
                null,
                $openedAt->toDateString()
            );

            $openingCash = isset($data['opening_cash']) ? round((float) $data['opening_cash'], 2) : 0.00;

            return PosShift::create([
                'shift_number' => $shiftNumber,
                'user_id' => $userId,
                'branch_id' => $branchId,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'status' => 'open',
                'opening_cash' => $openingCash,
                'closing_cash' => null,
                'actual_cash' => null,
                'difference' => 0.00,
                'total_sales' => 0.00,
                'total_returns' => 0.00,
                'total_cash' => 0.00,
                'total_card' => 0.00,
                'opened_at' => $openedAt,
                'closed_at' => null,
                'closed_by' => null,
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    public function close(PosShift $shift, array $data, int $userId): PosShift
    {
        if ($shift->status !== 'open') {
            throw new RuntimeException('الوردية مغلقة بالفعل مسبقاً.');
        }

        return DB::transaction(function () use ($shift, $data, $userId): PosShift {
            $totals = $this->calculateShiftTotals($shift);

            $actualCash = round((float) $data['actual_cash'], 2);
            $expectedCash = round((float) $shift->opening_cash + $totals['net_cash'], 2);
            $difference = round($actualCash - $expectedCash, 2);

            $shift->update([
                'status' => 'closed',
                'closing_cash' => $expectedCash,
                'actual_cash' => $actualCash,
                'difference' => $difference,
                'total_sales' => $totals['total_sales'],
                'total_returns' => $totals['total_returns'],
                'total_cash' => $totals['net_cash'],
                'total_card' => $totals['net_card'],
                'closed_at' => Carbon::now(),
                'closed_by' => $userId,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $shift->notes,
            ]);

            return $this->find($shift->id);
        });
    }

    public function calculateShiftTotals(PosShift $shift): array
    {
        // 1. إجمالي المبيعات المؤكدة وغير الملغاة
        $totalSales = (float) $shift->invoices()
            ->where('status', '!=', 'cancelled')
            ->sum('total_amount');

        // 2. إجمالي المرتجعات المؤكدة وغير الملغاة
        $totalReturns = (float) $shift->returns()
            ->where('status', '!=', 'cancelled')
            ->sum('total_amount');

        // 3. مقبوضات المبيعات النقدية وشبكة البطاقات
        $cashSalesPayments = (float) SalesInvoicePayment::where('shift_id', $shift->id)
            ->where('payment_method', 'cash')
            ->sum('amount');

        $cardSalesPayments = (float) SalesInvoicePayment::where('shift_id', $shift->id)
            ->where('payment_method', 'card')
            ->sum('amount');

        // 4. مدفوعات استرداد المرتجعات النقدية والشبكة
        $cashReturnRefunds = (float) SalesReturnPayment::where('shift_id', $shift->id)
            ->where('payment_method', 'cash')
            ->sum('amount');

        $cardReturnRefunds = (float) SalesReturnPayment::where('shift_id', $shift->id)
            ->where('payment_method', 'card')
            ->sum('amount');

        // 5. صافي النقد وصافي البطاقات للوردية
        $netCash = round($cashSalesPayments - $cashReturnRefunds, 2);
        $netCard = round($cardSalesPayments - $cardReturnRefunds, 2);

        return [
            'total_sales' => round($totalSales, 2),
            'total_returns' => round($totalReturns, 2),
            'cash_sales' => round($cashSalesPayments, 2),
            'card_sales' => round($cardSalesPayments, 2),
            'cash_refunds' => round($cashReturnRefunds, 2),
            'card_refunds' => round($cardReturnRefunds, 2),
            'net_cash' => $netCash,
            'net_card' => $netCard,
        ];
    }
}