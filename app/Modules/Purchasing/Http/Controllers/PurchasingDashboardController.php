<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Inventory\Enums\StockSerialStatus;
use App\Modules\Inventory\Models\StockSerial;
use App\Modules\Purchasing\Enums\BillStatus;
use App\Modules\Purchasing\Enums\IssueStatus;
use App\Modules\Purchasing\Enums\PurchaseOrderStatus;
use App\Modules\Purchasing\Enums\PurchaseReturnStatus;
use App\Modules\Purchasing\Enums\ReceiptStatus;
use App\Modules\Purchasing\Enums\RequisitionPriority;
use App\Modules\Purchasing\Enums\RequisitionStatus;
use App\Modules\Purchasing\Models\PurchaseBill;
use App\Modules\Purchasing\Models\PurchaseBillItem;
use App\Modules\Purchasing\Models\PurchaseIssue;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseReceipt;
use App\Modules\Purchasing\Models\PurchaseReceiptItem;
use App\Modules\Purchasing\Models\PurchaseRequisition;
use App\Modules\Purchasing\Models\PurchaseReturn;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class PurchasingDashboardController extends Controller
{
    /**
     * عرض البيانات التحليلية والإحصائية الشاملة للوحة تحكم موديول المشتريات وسلسلة الإمداد
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! $user->can('purchasing.dashboard.view')) {
            return response()->json([
                'message' => 'عذراً، ليس لديك صلاحية الوصول إلى لوحة تحكم المشتريات.',
            ], 403);
        }

        $validated = $request->validate([
            'date_from'     => ['nullable', 'date'],
            'date_to'       => ['nullable', 'date', 'after_or_equal:date_from'],
            'warehouse_id'  => ['nullable', 'integer', 'min:1'],
            'department_id' => ['nullable', 'integer', 'min:1'],
            'supplier_id'   => ['nullable', 'integer', 'min:1'],
        ]);

        $scope = $this->resolvePeriodAndScope($validated);

        /** @var Carbon $startDate */
        $startDate = $scope['start_date'];
        /** @var Carbon $endDate */
        $endDate = $scope['end_date'];
        /** @var Carbon $prevStartDate */
        $prevStartDate = $scope['prev_start_date'];
        /** @var Carbon $prevEndDate */
        $prevEndDate = $scope['prev_end_date'];
        /** @var array<string, int|null> $filters */
        $filters = $scope['filters'];
        /** @var Currency|null $baseCurrency */
        $baseCurrency = $scope['base_currency'];

        // 1. بطاقات المؤشرات الرئيسية (KPIs) ونسب النمو والـ Sparklines
        $kpis = $this->buildKpis($startDate, $endDate, $prevStartDate, $prevEndDate, $filters);

        // 2. بيانات الرسوم البيانية الستة (6 Charts)
        $charts = [
            'purchasing_trend'       => $this->buildPurchasingTrendChart($startDate, $endDate, $filters),
            'top_suppliers'          => $this->buildTopSuppliersChart($startDate, $endDate, $filters),
            'top_purchased_products' => $this->buildTopPurchasedProductsChart($startDate, $endDate, $filters),
            'department_consumption' => $this->buildDepartmentConsumptionChart($startDate, $endDate, $filters),
            'procurement_funnel'     => $this->buildProcurementFunnelChart($startDate, $endDate, $filters),
            'supplier_quality'       => $this->buildSupplierQualityChart($startDate, $endDate, $filters),
        ];

        // 3. التنبيهات التشغيلية، أوامر الشراء المفتوحة، وأحدث الفواتير
        $operations = $this->buildAlertsAndLists($filters);

        return response()->json([
            'status' => true,
            'data'   => [
                'meta' => [
                    'date_from'     => $startDate->format('Y-m-d'),
                    'date_to'       => $endDate->format('Y-m-d'),
                    'warehouse_id'  => $filters['warehouse_id'],
                    'department_id' => $filters['department_id'],
                    'supplier_id'   => $filters['supplier_id'],
                    'base_currency' => $baseCurrency ? [
                        'id'     => $baseCurrency->id,
                        'code'   => $baseCurrency->code,
                        'name'   => $baseCurrency->name,
                        'symbol' => $baseCurrency->symbol,
                    ] : [
                        'id'     => null,
                        'code'   => 'SDG',
                        'name'   => 'العملة الأساسية',
                        'symbol' => '',
                    ],
                ],
                'kpis'                 => $kpis,
                'charts'               => $charts,
                'alerts'               => $operations['alerts'],
                'open_purchase_orders' => $operations['open_purchase_orders'],
                'recent_bills'         => $operations['recent_bills'],
            ],
        ]);
    }

    /**
     * تحديد النطاق الزمني والفترة المقارنة السابقة والفلاتر النشطة
     *
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function resolvePeriodAndScope(array $validated): array
    {
        $today = Carbon::today();

        $startDate = ! empty($validated['date_from'])
            ? Carbon::parse((string) $validated['date_from'])->startOfDay()
            : $today->copy()->startOfMonth()->startOfDay();

        $endDate = ! empty($validated['date_to'])
            ? Carbon::parse((string) $validated['date_to'])->endOfDay()
            : $today->copy()->endOfDay();

        if ($endDate->lt($startDate)) {
            $endDate = $startDate->copy()->endOfDay();
        }

        $daysCount = max(1, (int) $startDate->diffInDays($endDate) + 1);
        $prevEndDate = $startDate->copy()->subDay()->endOfDay();
        $prevStartDate = $prevEndDate->copy()->subDays($daysCount - 1)->startOfDay();

        $baseCurrency = Currency::where('is_base', true)->first() ?? Currency::first();

        return [
            'start_date'      => $startDate,
            'end_date'        => $endDate,
            'prev_start_date' => $prevStartDate,
            'prev_end_date'   => $prevEndDate,
            'base_currency'   => $baseCurrency,
            'filters'         => [
                'warehouse_id'  => isset($validated['warehouse_id']) ? (int) $validated['warehouse_id'] : null,
                'department_id' => isset($validated['department_id']) ? (int) $validated['department_id'] : null,
                'supplier_id'   => isset($validated['supplier_id']) ? (int) $validated['supplier_id'] : null,
            ],
        ];
    }

    /**
     * بناء بطاقات المؤشرات الرئيسية (KPIs) ونسب النمو والـ Sparklines
     *
     * @param array<string, int|null> $filters
     * @return array<string, mixed>
     */
    private function buildKpis(
        Carbon $startDate,
        Carbon $endDate,
        Carbon $prevStartDate,
        Carbon $prevEndDate,
        array $filters
    ): array {
        $current = $this->calculatePeriodMetrics($startDate, $endDate, $filters);
        $previous = $this->calculatePeriodMetrics($prevStartDate, $prevEndDate, $filters);

        // إجمالي مستحقات الموردين التراكمية غير المسددة من الفواتير المرحّلة
        $postedBillStatuses = [
            BillStatus::POSTED->value,
            BillStatus::PAID->value,
            BillStatus::PARTIALLY_PAID->value,
        ];

        $payablesQuery = PurchaseBill::query()
            ->whereIn('status', $postedBillStatuses)
            ->where('remaining_amount', '>', 0);

        $this->applyBillScopeFilters($payablesQuery, $filters);

        $totalPayables = (float) ($payablesQuery->selectRaw(
            'COALESCE(SUM(remaining_amount * COALESCE(NULLIF(exchange_rate, 0), 1)), 0) as total'
        )->value('total') ?? 0.0);

        $overduePayables = (float) ((clone $payablesQuery)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', Carbon::today()->toDateString())
            ->selectRaw('COALESCE(SUM(remaining_amount * COALESCE(NULLIF(exchange_rate, 0), 1)), 0) as total')
            ->value('total') ?? 0.0);

        $sparklines = $this->buildDailySparklines($endDate, $filters);

        return [
            'gross_purchases' => [
                'value'             => $current['gross_purchases'],
                'previous_value'    => $previous['gross_purchases'],
                'bills_count'       => $current['bills_count'],
                'growth_percentage' => $this->calculateGrowth($current['gross_purchases'], $previous['gross_purchases']),
                'sparkline'         => $sparklines['gross_purchases'],
            ],
            'total_returns' => [
                'value'             => $current['total_returns'],
                'previous_value'    => $previous['total_returns'],
                'return_rate'       => $current['return_rate'],
                'growth_percentage' => $this->calculateGrowth($current['total_returns'], $previous['total_returns']),
                'sparkline'         => $sparklines['total_returns'],
            ],
            'net_purchases' => [
                'value'             => $current['net_purchases'],
                'previous_value'    => $previous['net_purchases'],
                'growth_percentage' => $this->calculateGrowth($current['net_purchases'], $previous['net_purchases']),
                'sparkline'         => $sparklines['net_purchases'],
            ],
            'payables' => [
                'value'          => round($totalPayables, 2),
                'overdue_amount' => round($overduePayables, 2),
            ],
            'payments_made' => [
                'value'             => $current['payments_made'],
                'previous_value'    => $previous['payments_made'],
                'growth_percentage' => $this->calculateGrowth($current['payments_made'], $previous['payments_made']),
            ],
            'internal_issues_cost' => [
                'value'             => $current['internal_issues_cost'],
                'previous_value'    => $previous['internal_issues_cost'],
                'issues_count'      => $current['issues_count'],
                'growth_percentage' => $this->calculateGrowth($current['internal_issues_cost'], $previous['internal_issues_cost']),
                'sparkline'         => $sparklines['internal_issues'],
            ],
            'quality_acceptance' => [
                'acceptance_rate'   => $current['acceptance_rate'],
                'previous_rate'     => $previous['acceptance_rate'],
                'accepted_quantity' => $current['accepted_quantity'],
                'rejected_quantity' => $current['rejected_quantity'],
            ],
        ];
    }

    /**
     * احتساب المقاييس المالية والتشغيلية للمشتريات خلال فترة محددة
     *
     * @param array<string, int|null> $filters
     * @return array<string, mixed>
     */
    private function calculatePeriodMetrics(Carbon $startDate, Carbon $endDate, array $filters): array
    {
        $fromStr = $startDate->toDateString();
        $toStr = $endDate->toDateString();

        $postedBillStatuses = [
            BillStatus::POSTED->value,
            BillStatus::PAID->value,
            BillStatus::PARTIALLY_PAID->value,
        ];

        // 1. فواتير المشتريات المرحّلة
        $billsQuery = PurchaseBill::query()
            ->whereIn('status', $postedBillStatuses)
            ->whereDate('bill_date', '>=', $fromStr)
            ->whereDate('bill_date', '<=', $toStr);

        $this->applyBillScopeFilters($billsQuery, $filters);

        $billStats = $billsQuery->selectRaw('
            COUNT(*) as bills_count,
            COALESCE(SUM(total_amount * COALESCE(NULLIF(exchange_rate, 0), 1)), 0) as gross_purchases,
            COALESCE(SUM(paid_amount * COALESCE(NULLIF(exchange_rate, 0), 1)), 0) as paid_total
        ')->first();

        $billsCount = (int) ($billStats->bills_count ?? 0);
        $grossPurchases = (float) ($billStats->gross_purchases ?? 0.0);
        $paymentsMade = (float) ($billStats->paid_total ?? 0.0);

        // 2. مردودات المشتريات المرحّلة
        $returnsQuery = PurchaseReturn::query()
            ->where('status', PurchaseReturnStatus::POSTED->value)
            ->whereDate('return_date', '>=', $fromStr)
            ->whereDate('return_date', '<=', $toStr);

        $this->applyReturnScopeFilters($returnsQuery, $filters);

        $totalReturns = (float) ($returnsQuery->selectRaw(
            'COALESCE(SUM(total_amount * COALESCE(NULLIF(exchange_rate, 0), 1)), 0) as total'
        )->value('total') ?? 0.0);

        // 3. تكلفة أذونات الصرف المخزني للأقسام
        $issuesQuery = PurchaseIssue::query()
            ->where('status', IssueStatus::ISSUED->value)
            ->whereDate('issue_date', '>=', $fromStr)
            ->whereDate('issue_date', '<=', $toStr);

        if ($filters['warehouse_id'] !== null) {
            $issuesQuery->where('warehouse_id', $filters['warehouse_id']);
        }
        if ($filters['department_id'] !== null) {
            $issuesQuery->where('department_id', $filters['department_id']);
        }

        $issueStats = $issuesQuery->selectRaw('
            COUNT(*) as issues_count,
            COALESCE(SUM(total_cost), 0) as total_cost
        ')->first();

        $issuesCount = (int) ($issueStats->issues_count ?? 0);
        $internalIssuesCost = (float) ($issueStats->total_cost ?? 0.0);

        // 4. جودة الاستلام المخزني (المقبول مقابل المرفوض)
        $receiptItemsQuery = PurchaseReceiptItem::query()
            ->join('purchasing_receipts', 'purchasing_receipt_items.receipt_id', '=', 'purchasing_receipts.id')
            ->whereNull('purchasing_receipts.deleted_at')
            ->whereNull('purchasing_receipt_items.deleted_at')
            ->where('purchasing_receipts.status', ReceiptStatus::RECEIVED->value)
            ->whereDate('purchasing_receipts.receipt_date', '>=', $fromStr)
            ->whereDate('purchasing_receipts.receipt_date', '<=', $toStr);

        $this->applyReceiptScopeFilters($receiptItemsQuery, $filters, 'purchasing_receipts');

        $qualityStats = $receiptItemsQuery->selectRaw('
            COALESCE(SUM(purchasing_receipt_items.quantity_received), 0) as received_qty,
            COALESCE(SUM(purchasing_receipt_items.quantity_accepted), 0) as accepted_qty,
            COALESCE(SUM(purchasing_receipt_items.quantity_rejected), 0) as rejected_qty
        ')->first();

        $receivedQty = (float) ($qualityStats->received_qty ?? 0.0);
        $acceptedQty = (float) ($qualityStats->accepted_qty ?? 0.0);
        $rejectedQty = (float) ($qualityStats->rejected_qty ?? 0.0);

        $acceptanceRate = $receivedQty > 0 ? round(($acceptedQty / $receivedQty) * 100, 1) : 100.0;
        $netPurchases = round($grossPurchases - $totalReturns, 2);
        $returnRate = $grossPurchases > 0 ? round(($totalReturns / $grossPurchases) * 100, 2) : 0.0;

        return [
            'bills_count'          => $billsCount,
            'gross_purchases'      => round($grossPurchases, 2),
            'total_returns'        => round($totalReturns, 2),
            'net_purchases'        => $netPurchases,
            'return_rate'          => $returnRate,
            'payments_made'        => round($paymentsMade, 2),
            'issues_count'         => $issuesCount,
            'internal_issues_cost' => round($internalIssuesCost, 2),
            'accepted_quantity'    => round($acceptedQty, 2),
            'rejected_quantity'    => round($rejectedQty, 2),
            'acceptance_rate'      => $acceptanceRate,
        ];
    }

    /**
     * Chart 1: المنحنى الزمني للمشتريات، المرتجعات، صافي المشتريات، والصرف الداخلي
     *
     * @param array<string, int|null> $filters
     * @return array<string, mixed>
     */
    private function buildPurchasingTrendChart(Carbon $startDate, Carbon $endDate, array $filters): array
    {
        $isMonthly = $startDate->diffInDays($endDate) > 31;
        $keyFormat = $isMonthly ? 'Y-m' : 'Y-m-d';
        $step = $isMonthly ? '1 month' : '1 day';

        $arabicMonths = [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
            5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
        ];

        $buckets = [];
        $period = CarbonPeriod::create(
            $isMonthly ? $startDate->copy()->startOfMonth() : $startDate->copy()->startOfDay(),
            $step,
            $isMonthly ? $endDate->copy()->endOfMonth() : $endDate->copy()->endOfDay()
        );

        foreach ($period as $dt) {
            $k = $dt->format($keyFormat);
            $label = $isMonthly
                ? ($arabicMonths[(int) $dt->month] . ' ' . $dt->year)
                : $dt->format('m-d');

            $buckets[$k] = [
                'label'           => $label,
                'purchases'       => 0.0,
                'returns'         => 0.0,
                'internal_issues' => 0.0,
            ];
        }

        $fromStr = $startDate->toDateString();
        $toStr = $endDate->toDateString();

        // 1. فواتير المشتريات
        $billsQuery = PurchaseBill::query()
            ->whereIn('status', [
                BillStatus::POSTED->value,
                BillStatus::PAID->value,
                BillStatus::PARTIALLY_PAID->value,
            ])
            ->whereDate('bill_date', '>=', $fromStr)
            ->whereDate('bill_date', '<=', $toStr);

        $this->applyBillScopeFilters($billsQuery, $filters);

        $bills = $billsQuery->select(['bill_date', 'total_amount', 'exchange_rate'])->get();
        foreach ($bills as $bill) {
            $k = Carbon::parse($bill->bill_date)->format($keyFormat);
            if (isset($buckets[$k])) {
                $rate = (float) $bill->exchange_rate > 0 ? (float) $bill->exchange_rate : 1.0;
                $buckets[$k]['purchases'] += ((float) $bill->total_amount * $rate);
            }
        }

        // 2. مرتجعات المشتريات
        $returnsQuery = PurchaseReturn::query()
            ->where('status', PurchaseReturnStatus::POSTED->value)
            ->whereDate('return_date', '>=', $fromStr)
            ->whereDate('return_date', '<=', $toStr);

        $this->applyReturnScopeFilters($returnsQuery, $filters);

        $returns = $returnsQuery->select(['return_date', 'total_amount', 'exchange_rate'])->get();
        foreach ($returns as $ret) {
            $k = Carbon::parse($ret->return_date)->format($keyFormat);
            if (isset($buckets[$k])) {
                $rate = (float) $ret->exchange_rate > 0 ? (float) $ret->exchange_rate : 1.0;
                $buckets[$k]['returns'] += ((float) $ret->total_amount * $rate);
            }
        }

        // 3. الصرف الداخلي للأقسام
        $issuesQuery = PurchaseIssue::query()
            ->where('status', IssueStatus::ISSUED->value)
            ->whereDate('issue_date', '>=', $fromStr)
            ->whereDate('issue_date', '<=', $toStr);

        if ($filters['warehouse_id'] !== null) {
            $issuesQuery->where('warehouse_id', $filters['warehouse_id']);
        }
        if ($filters['department_id'] !== null) {
            $issuesQuery->where('department_id', $filters['department_id']);
        }

        $issues = $issuesQuery->select(['issue_date', 'total_cost'])->get();
        foreach ($issues as $iss) {
            $k = Carbon::parse($iss->issue_date)->format($keyFormat);
            if (isset($buckets[$k])) {
                $buckets[$k]['internal_issues'] += (float) $iss->total_cost;
            }
        }

        $labels = [];
        $purchasesSeries = [];
        $returnsSeries = [];
        $netPurchasesSeries = [];
        $issuesSeries = [];

        foreach ($buckets as $b) {
            $p = round($b['purchases'], 2);
            $r = round($b['returns'], 2);
            $labels[] = $b['label'];
            $purchasesSeries[] = $p;
            $returnsSeries[] = $r;
            $netPurchasesSeries[] = round($p - $r, 2);
            $issuesSeries[] = round($b['internal_issues'], 2);
        }

        return [
            'granularity'     => $isMonthly ? 'monthly' : 'daily',
            'labels'          => $labels,
            'purchases'       => $purchasesSeries,
            'returns'         => $returnsSeries,
            'net_purchases'   => $netPurchasesSeries,
            'internal_issues' => $issuesSeries,
        ];
    }

    /**
     * Chart 2: أعلى 6 موردين من حيث حجم التعامل والمستحقات
     *
     * @param array<string, int|null> $filters
     * @return array<string, mixed>
     */
    private function buildTopSuppliersChart(Carbon $startDate, Carbon $endDate, array $filters): array
    {
        $query = PurchaseBill::query()
            ->join('partners', 'purchasing_bills.supplier_id', '=', 'partners.id')
            ->whereNull('purchasing_bills.deleted_at')
            ->whereIn('purchasing_bills.status', [
                BillStatus::POSTED->value,
                BillStatus::PAID->value,
                BillStatus::PARTIALLY_PAID->value,
            ])
            ->whereDate('purchasing_bills.bill_date', '>=', $startDate->toDateString())
            ->whereDate('purchasing_bills.bill_date', '<=', $endDate->toDateString());

        $this->applyBillScopeFilters($query, $filters, 'purchasing_bills');

        $rows = $query->selectRaw('
                partners.id as supplier_id,
                partners.name as supplier_name,
                partners.phone as supplier_phone,
                COUNT(purchasing_bills.id) as bills_count,
                COALESCE(SUM(purchasing_bills.total_amount * COALESCE(NULLIF(purchasing_bills.exchange_rate, 0), 1)), 0) as total_purchases,
                COALESCE(SUM(purchasing_bills.remaining_amount * COALESCE(NULLIF(purchasing_bills.exchange_rate, 0), 1)), 0) as unpaid_balance
            ')
            ->groupBy('partners.id', 'partners.name', 'partners.phone')
            ->orderByDesc('total_purchases')
            ->limit(6)
            ->get();

        $items = $rows->map(function ($row) {
            return [
                'supplier_id'     => (int) $row->supplier_id,
                'name'            => $row->supplier_name,
                'phone'           => $row->supplier_phone,
                'bills_count'     => (int) $row->bills_count,
                'total_purchases' => round((float) $row->total_purchases, 2),
                'unpaid_balance'  => round((float) $row->unpaid_balance, 2),
            ];
        })->values();

        return [
            'labels' => $items->pluck('name')->toArray(),
            'series' => $items->pluck('total_purchases')->toArray(),
            'items'  => $items->toArray(),
        ];
    }

    /**
     * Chart 3: أعلى 6 أصناف شراءً من حيث التكلفة الإجمالية والكمية
     *
     * @param array<string, int|null> $filters
     * @return array<string, mixed>
     */
    private function buildTopPurchasedProductsChart(Carbon $startDate, Carbon $endDate, array $filters): array
    {
        $query = PurchaseBillItem::query()
            ->join('purchasing_bills', 'purchasing_bill_items.bill_id', '=', 'purchasing_bills.id')
            ->join('inventory_products', 'purchasing_bill_items.product_id', '=', 'inventory_products.id')
            ->whereNull('purchasing_bills.deleted_at')
            ->whereNull('purchasing_bill_items.deleted_at')
            ->whereNull('inventory_products.deleted_at')
            ->whereIn('purchasing_bills.status', [
                BillStatus::POSTED->value,
                BillStatus::PAID->value,
                BillStatus::PARTIALLY_PAID->value,
            ])
            ->whereDate('purchasing_bills.bill_date', '>=', $startDate->toDateString())
            ->whereDate('purchasing_bills.bill_date', '<=', $endDate->toDateString());

        $this->applyBillScopeFilters($query, $filters, 'purchasing_bills');

        $rows = $query->selectRaw('
                inventory_products.id as product_id,
                inventory_products.name as product_name,
                COALESCE(SUM(purchasing_bill_items.quantity), 0) as total_qty,
                COALESCE(SUM(purchasing_bill_items.total * COALESCE(NULLIF(purchasing_bills.exchange_rate, 0), 1)), 0) as total_cost
            ')
            ->groupBy('inventory_products.id', 'inventory_products.name')
            ->orderByDesc('total_cost')
            ->limit(6)
            ->get();

        $items = $rows->map(function ($row) {
            $qty = round((float) $row->total_qty, 2);
            $cost = round((float) $row->total_cost, 2);

            return [
                'product_id'     => (int) $row->product_id,
                'product_name'   => $row->product_name,
                'quantity'       => $qty,
                'total_cost'     => $cost,
                'avg_unit_price' => $qty > 0 ? round($cost / $qty, 2) : 0.0,
            ];
        })->values();

        return [
            'labels'          => $items->pluck('product_name')->toArray(),
            'cost_series'     => $items->pluck('total_cost')->toArray(),
            'quantity_series' => $items->pluck('quantity')->toArray(),
            'items'           => $items->toArray(),
        ];
    }

    /**
     * Chart 4: توزيع تكلفة الصرف المخزني الداخلي حسب الأقسام
     *
     * @param array<string, int|null> $filters
     * @return array<string, mixed>
     */
    private function buildDepartmentConsumptionChart(Carbon $startDate, Carbon $endDate, array $filters): array
    {
        $query = PurchaseIssue::query()
            ->leftJoin('departments', 'purchasing_issues.department_id', '=', 'departments.id')
            ->whereNull('purchasing_issues.deleted_at')
            ->where('purchasing_issues.status', IssueStatus::ISSUED->value)
            ->whereDate('purchasing_issues.issue_date', '>=', $startDate->toDateString())
            ->whereDate('purchasing_issues.issue_date', '<=', $endDate->toDateString());

        if ($filters['warehouse_id'] !== null) {
            $query->where('purchasing_issues.warehouse_id', $filters['warehouse_id']);
        }
        if ($filters['department_id'] !== null) {
            $query->where('purchasing_issues.department_id', $filters['department_id']);
        }

        $rows = $query->selectRaw('
                purchasing_issues.department_id as department_id,
                COALESCE(departments.name, \'صرف عام / غير محدد القسم\') as department_name,
                COUNT(purchasing_issues.id) as issues_count,
                COALESCE(SUM(purchasing_issues.total_cost), 0) as total_cost
            ')
            ->groupBy('purchasing_issues.department_id', 'departments.name')
            ->orderByDesc('total_cost')
            ->limit(8)
            ->get();

        $items = $rows->map(function ($row) {
            return [
                'department_id'   => $row->department_id ? (int) $row->department_id : null,
                'department_name' => $row->department_name,
                'issues_count'    => (int) $row->issues_count,
                'total_cost'      => round((float) $row->total_cost, 2),
            ];
        })->values();

        return [
            'labels' => $items->pluck('department_name')->toArray(),
            'series' => $items->pluck('total_cost')->toArray(),
            'counts' => $items->pluck('issues_count')->toArray(),
            'items'  => $items->toArray(),
        ];
    }

    /**
     * Chart 5: قمع ومؤشرات دورة الشراء (حالات طلبات الاحتياج وأوامر الشراء)
     *
     * @param array<string, int|null> $filters
     * @return array<string, mixed>
     */
    private function buildProcurementFunnelChart(Carbon $startDate, Carbon $endDate, array $filters): array
    {
        $fromStr = $startDate->toDateString();
        $toStr = $endDate->toDateString();

        // 1. حالات طلبات الاحتياج الداخلي
        $reqQuery = PurchaseRequisition::query()
            ->whereDate('request_date', '>=', $fromStr)
            ->whereDate('request_date', '<=', $toStr);

        if ($filters['department_id'] !== null) {
            $reqQuery->where('department_id', $filters['department_id']);
        }

        $reqRows = $reqQuery->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->get();

        $requisitionsBreakdown = $reqRows->map(function ($row) {
            $val = $row->status instanceof RequisitionStatus ? $row->status->value : (string) $row->status;
            $enum = RequisitionStatus::tryFrom($val);

            return [
                'status' => $val,
                'label'  => $enum ? $enum->label() : $val,
                'count'  => (int) $row->cnt,
            ];
        })->values();

        // 2. حالات أوامر الشراء
        $poQuery = PurchaseOrder::query()
            ->whereDate('order_date', '>=', $fromStr)
            ->whereDate('order_date', '<=', $toStr);

        if ($filters['department_id'] !== null) {
            $poQuery->where('department_id', $filters['department_id']);
        }
        if ($filters['supplier_id'] !== null) {
            $poQuery->where('supplier_id', $filters['supplier_id']);
        }

        $poRows = $poQuery->selectRaw('
                status,
                COUNT(*) as cnt,
                COALESCE(SUM(total_amount * COALESCE(NULLIF(exchange_rate, 0), 1)), 0) as total_amount
            ')
            ->groupBy('status')
            ->get();

        $ordersBreakdown = $poRows->map(function ($row) {
            $val = $row->status instanceof PurchaseOrderStatus ? $row->status->value : (string) $row->status;
            $enum = PurchaseOrderStatus::tryFrom($val);

            return [
                'status'       => $val,
                'label'        => $enum ? $enum->label() : $val,
                'count'        => (int) $row->cnt,
                'total_amount' => round((float) $row->total_amount, 2),
            ];
        })->values();

        return [
            'requisitions' => [
                'labels' => $requisitionsBreakdown->pluck('label')->toArray(),
                'series' => $requisitionsBreakdown->pluck('count')->toArray(),
                'items'  => $requisitionsBreakdown->toArray(),
            ],
            'purchase_orders' => [
                'labels' => $ordersBreakdown->pluck('label')->toArray(),
                'series' => $ordersBreakdown->pluck('count')->toArray(),
                'amounts'=> $ordersBreakdown->pluck('total_amount')->toArray(),
                'items'  => $ordersBreakdown->toArray(),
            ],
        ];
    }

    /**
     * Chart 6: تقييم جودة التوريد للموردين (الكميات المقبولة مقابل المرفوضة عند الاستلام)
     *
     * @param array<string, int|null> $filters
     * @return array<string, mixed>
     */
    private function buildSupplierQualityChart(Carbon $startDate, Carbon $endDate, array $filters): array
    {
        $query = PurchaseReceiptItem::query()
            ->join('purchasing_receipts', 'purchasing_receipt_items.receipt_id', '=', 'purchasing_receipts.id')
            ->join('partners', 'purchasing_receipts.supplier_id', '=', 'partners.id')
            ->whereNull('purchasing_receipts.deleted_at')
            ->whereNull('purchasing_receipt_items.deleted_at')
            ->where('purchasing_receipts.status', ReceiptStatus::RECEIVED->value)
            ->whereDate('purchasing_receipts.receipt_date', '>=', $startDate->toDateString())
            ->whereDate('purchasing_receipts.receipt_date', '<=', $endDate->toDateString());

        $this->applyReceiptScopeFilters($query, $filters, 'purchasing_receipts');

        $rows = $query->selectRaw('
                partners.id as supplier_id,
                partners.name as supplier_name,
                COALESCE(SUM(purchasing_receipt_items.quantity_received), 0) as received_qty,
                COALESCE(SUM(purchasing_receipt_items.quantity_accepted), 0) as accepted_qty,
                COALESCE(SUM(purchasing_receipt_items.quantity_rejected), 0) as rejected_qty
            ')
            ->groupBy('partners.id', 'partners.name')
            ->orderByDesc('received_qty')
            ->limit(6)
            ->get();

        $items = $rows->map(function ($row) {
            $rec = (float) $row->received_qty;
            $acc = (float) $row->accepted_qty;
            $rej = (float) $row->rejected_qty;

            return [
                'supplier_id'       => (int) $row->supplier_id,
                'supplier_name'     => $row->supplier_name,
                'quantity_received' => round($rec, 2),
                'quantity_accepted' => round($acc, 2),
                'quantity_rejected' => round($rej, 2),
                'acceptance_rate'   => $rec > 0 ? round(($acc / $rec) * 100, 1) : 100.0,
            ];
        })->values();

        return [
            'labels'          => $items->pluck('supplier_name')->toArray(),
            'accepted_series' => $items->pluck('quantity_accepted')->toArray(),
            'rejected_series' => $items->pluck('quantity_rejected')->toArray(),
            'items'           => $items->toArray(),
        ];
    }

    /**
     * التنبيهات التشغيلية، أوامر الشراء المفتوحة، وأحدث فواتير المشتريات
     *
     * @param array<string, int|null> $filters
     * @return array<string, mixed>
     */
    private function buildAlertsAndLists(array $filters): array
    {
        $todayStr = Carbon::today()->toDateString();

        // 1. التنبيهات التشغيلية
        $pendingReqQuery = PurchaseRequisition::where('status', RequisitionStatus::PENDING_APPROVAL->value);
        $urgentReqQuery = PurchaseRequisition::where('priority', RequisitionPriority::URGENT->value)
            ->whereIn('status', [
                RequisitionStatus::DRAFT->value,
                RequisitionStatus::PENDING_APPROVAL->value,
                RequisitionStatus::APPROVED->value,
            ]);

        if ($filters['department_id'] !== null) {
            $pendingReqQuery->where('department_id', $filters['department_id']);
            $urgentReqQuery->where('department_id', $filters['department_id']);
        }

        $openPoStatuses = [
            PurchaseOrderStatus::CONFIRMED->value,
            PurchaseOrderStatus::PARTIALLY_RECEIVED->value,
            PurchaseOrderStatus::PARTIALLY_BILLED->value,
        ];

        $draftOrdersQuery = PurchaseOrder::where('status', PurchaseOrderStatus::DRAFT->value);
        $overdueOrdersQuery = PurchaseOrder::whereIn('status', $openPoStatuses)
            ->whereNotNull('expected_delivery_date')
            ->whereDate('expected_delivery_date', '<', $todayStr);

        if ($filters['department_id'] !== null) {
            $draftOrdersQuery->where('department_id', $filters['department_id']);
            $overdueOrdersQuery->where('department_id', $filters['department_id']);
        }
        if ($filters['supplier_id'] !== null) {
            $draftOrdersQuery->where('supplier_id', $filters['supplier_id']);
            $overdueOrdersQuery->where('supplier_id', $filters['supplier_id']);
        }

        $draftReceiptsQuery = PurchaseReceipt::where('status', ReceiptStatus::DRAFT->value);
        $this->applyReceiptScopeFilters($draftReceiptsQuery, $filters);

        $draftIssuesQuery = PurchaseIssue::where('status', IssueStatus::DRAFT->value);
        if ($filters['warehouse_id'] !== null) {
            $draftIssuesQuery->where('warehouse_id', $filters['warehouse_id']);
        }
        if ($filters['department_id'] !== null) {
            $draftIssuesQuery->where('department_id', $filters['department_id']);
        }

        $draftBillsQuery = PurchaseBill::where('status', BillStatus::DRAFT->value);
        $this->applyBillScopeFilters($draftBillsQuery, $filters);

        $overdueBillsQuery = PurchaseBill::whereIn('status', [
            BillStatus::POSTED->value,
            BillStatus::PARTIALLY_PAID->value,
        ])
            ->where('remaining_amount', '>', 0)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $todayStr);
        $this->applyBillScopeFilters($overdueBillsQuery, $filters);

        // عدد الأسطوانات / السيريالات العالقة لدى الموردين للتعبئة
        $pendingVendorSerialsCount = 0;
        try {
            if (class_exists(StockSerial::class)) {
                $serialsQuery = StockSerial::where('status', StockSerialStatus::WITH_VENDOR->value);
                if ($filters['warehouse_id'] !== null) {
                    $serialsQuery->where('warehouse_id', $filters['warehouse_id']);
                }
                $pendingVendorSerialsCount = $serialsQuery->count();
            }
        } catch (Throwable) {
            $pendingVendorSerialsCount = 0;
        }

        // 2. أحدث 5 أوامر شراء مفتوحة ونسبة إنجاز التوريد
        $openOrdersQuery = PurchaseOrder::query()
            ->whereIn('status', [
                PurchaseOrderStatus::CONFIRMED->value,
                PurchaseOrderStatus::PARTIALLY_RECEIVED->value,
                PurchaseOrderStatus::RECEIVED->value,
                PurchaseOrderStatus::PARTIALLY_BILLED->value,
            ])
            ->with(['supplier:id,name', 'department:id,name', 'currency:id,code,symbol'])
            ->withSum('items as total_ordered_qty', 'quantity')
            ->withSum('items as total_received_qty', 'received_quantity');

        if ($filters['department_id'] !== null) {
            $openOrdersQuery->where('department_id', $filters['department_id']);
        }
        if ($filters['supplier_id'] !== null) {
            $openOrdersQuery->where('supplier_id', $filters['supplier_id']);
        }

        $openPurchaseOrders = $openOrdersQuery->orderByDesc('order_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(function (PurchaseOrder $order) use ($todayStr) {
                $orderedQty = (float) ($order->total_ordered_qty ?? 0.0);
                $receivedQty = (float) ($order->total_received_qty ?? 0.0);
                $progress = $orderedQty > 0 ? min(100.0, round(($receivedQty / $orderedQty) * 100, 1)) : 0.0;
                $statusEnum = $order->status instanceof PurchaseOrderStatus
                    ? $order->status
                    : PurchaseOrderStatus::tryFrom((string) $order->status);

                return [
                    'id'                     => $order->id,
                    'order_number'           => $order->order_number,
                    'supplier_name'          => $order->supplier?->name,
                    'department_name'        => $order->department?->name,
                    'order_date'             => $order->order_date?->format('Y-m-d'),
                    'expected_delivery_date' => $order->expected_delivery_date?->format('Y-m-d'),
                    'is_overdue'             => $order->expected_delivery_date !== null && $order->expected_delivery_date->format('Y-m-d') < $todayStr && $progress < 100.0,
                    'status'                 => $statusEnum?->value ?? (string) $order->status,
                    'status_label'           => $statusEnum?->label() ?? (string) $order->status,
                    'total_amount'           => round((float) $order->total_amount, 2),
                    'currency_symbol'        => $order->currency?->symbol ?? '',
                    'receipt_progress'       => $progress,
                ];
            })->values()->toArray();

        // 3. أحدث 8 فواتير مشتريات
        $recentBillsQuery = PurchaseBill::query()
            ->with(['supplier:id,name', 'warehouse:id,name', 'currency:id,code,symbol']);
        $this->applyBillScopeFilters($recentBillsQuery, $filters);

        $recentBills = $recentBillsQuery->orderByDesc('bill_date')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(function (PurchaseBill $bill) {
                $statusEnum = $bill->status instanceof BillStatus
                    ? $bill->status
                    : BillStatus::tryFrom((string) $bill->status);

                return [
                    'id'               => $bill->id,
                    'bill_number'      => $bill->bill_number,
                    'supplier_name'    => $bill->supplier?->name,
                    'warehouse_name'   => $bill->warehouse?->name,
                    'bill_date'        => $bill->bill_date?->format('Y-m-d'),
                    'due_date'         => $bill->due_date?->format('Y-m-d'),
                    'status'           => $statusEnum?->value ?? (string) $bill->status,
                    'status_label'     => $statusEnum?->label() ?? (string) $bill->status,
                    'total_amount'     => round((float) $bill->total_amount, 2),
                    'paid_amount'      => round((float) $bill->paid_amount, 2),
                    'remaining_amount' => round((float) $bill->remaining_amount, 2),
                    'currency_symbol'  => $bill->currency?->symbol ?? '',
                ];
            })->values()->toArray();

        return [
            'alerts' => [
                'pending_requisitions_count'   => $pendingReqQuery->count(),
                'urgent_requisitions_count'    => $urgentReqQuery->count(),
                'draft_orders_count'           => $draftOrdersQuery->count(),
                'overdue_orders_count'         => $overdueOrdersQuery->count(),
                'draft_receipts_count'         => $draftReceiptsQuery->count(),
                'draft_issues_count'           => $draftIssuesQuery->count(),
                'draft_bills_count'            => $draftBillsQuery->count(),
                'overdue_bills_count'          => $overdueBillsQuery->count(),
                'pending_vendor_serials_count' => $pendingVendorSerialsCount,
            ],
            'open_purchase_orders' => $openPurchaseOrders,
            'recent_bills'         => $recentBills,
        ];
    }

    /**
     * بناء نقاط Sparkline اليومية لآخر 7 أيام
     *
     * @param array<string, int|null> $filters
     * @return array<string, array<int, float>>
     */
    private function buildDailySparklines(Carbon $endDate, array $filters): array
    {
        $start7D = $endDate->copy()->subDays(6)->startOfDay();
        $trend = $this->buildPurchasingTrendChart($start7D, $endDate, $filters);

        return [
            'gross_purchases' => $trend['purchases'],
            'total_returns'   => $trend['returns'],
            'net_purchases'   => $trend['net_purchases'],
            'internal_issues' => $trend['internal_issues'],
        ];
    }

    /**
     * تطبيق فلاتر النطاق على استعلامات فواتير المشتريات
     *
     * @param Builder $query
     * @param array<string, int|null> $filters
     */
    private function applyBillScopeFilters(Builder $query, array $filters, string $table = ''): void
    {
        $prefix = $table !== '' ? "{$table}." : '';

        if ($filters['warehouse_id'] !== null) {
            $query->where("{$prefix}warehouse_id", $filters['warehouse_id']);
        }
        if ($filters['supplier_id'] !== null) {
            $query->where("{$prefix}supplier_id", $filters['supplier_id']);
        }
        if ($filters['department_id'] !== null) {
            $departmentId = $filters['department_id'];
            $query->whereHas('purchaseOrder', fn (Builder $q) => $q->where('department_id', $departmentId));
        }
    }

    /**
     * تطبيق فلاتر النطاق على استعلامات مرتجعات المشتريات
     *
     * @param Builder $query
     * @param array<string, int|null> $filters
     */
    private function applyReturnScopeFilters(Builder $query, array $filters, string $table = ''): void
    {
        $prefix = $table !== '' ? "{$table}." : '';

        if ($filters['warehouse_id'] !== null) {
            $query->where("{$prefix}warehouse_id", $filters['warehouse_id']);
        }
        if ($filters['supplier_id'] !== null) {
            $query->where("{$prefix}supplier_id", $filters['supplier_id']);
        }
        if ($filters['department_id'] !== null) {
            $departmentId = $filters['department_id'];
            $query->whereHas('bill.purchaseOrder', fn (Builder $q) => $q->where('department_id', $departmentId));
        }
    }

    /**
     * تطبيق فلاتر النطاق على استعلامات سندات الاستلام
     *
     * @param Builder $query
     * @param array<string, int|null> $filters
     */
    private function applyReceiptScopeFilters(Builder $query, array $filters, string $table = ''): void
    {
        $prefix = $table !== '' ? "{$table}." : '';

        if ($filters['warehouse_id'] !== null) {
            $query->where("{$prefix}warehouse_id", $filters['warehouse_id']);
        }
        if ($filters['supplier_id'] !== null) {
            $query->where("{$prefix}supplier_id", $filters['supplier_id']);
        }
        if ($filters['department_id'] !== null) {
            $departmentId = $filters['department_id'];
            $query->whereHas('purchaseOrder', fn (Builder $q) => $q->where('department_id', $departmentId));
        }
    }

    /**
     * حساب نسبة النمو المئوية مقارنة بالفترة السابقة
     */
    private function calculateGrowth(float $current, float $previous): float
    {
        if (abs($previous) < 0.0001) {
            return abs($current) < 0.0001 ? 0.0 : 100.0;
        }

        return round((($current - $previous) / abs($previous)) * 100, 1);
    }
}