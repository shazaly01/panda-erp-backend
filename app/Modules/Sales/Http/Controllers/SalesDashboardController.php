<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Sales\Models\PosShift;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesInvoiceItem;
use App\Modules\Sales\Models\SalesInvoicePayment;
use App\Modules\Sales\Models\SalesReturn;
use App\Modules\Sales\Models\SalesReturnItem;
use App\Modules\Sales\Models\SalesReturnPayment;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesDashboardController extends Controller
{
    /**
     * عرض البيانات التحليلية والإحصائية الشاملة للوحة تحكم المبيعات ونقاط البيع
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! $user->can('sales.dashboard.view')) {
            return response()->json([
                'message' => 'عذراً، ليس لديك صلاحية الوصول إلى لوحة تحكم المبيعات.',
            ], 403);
        }

        $validated = $request->validate([
            'date_from'    => ['nullable', 'date'],
            'date_to'      => ['nullable', 'date', 'after_or_equal:date_from'],
            'branch_id'    => ['nullable', 'integer', 'min:1'],
            'warehouse_id' => ['nullable', 'integer', 'min:1'],
            'channel'      => ['nullable', 'string', 'max:50'],
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
        /** @var array<string, mixed> $filters */
        $filters = $scope['filters'];
        /** @var Currency|null $baseCurrency */
        $baseCurrency = $scope['base_currency'];

        // 1. بطاقات المؤشرات الرئيسية (KPIs) ونسب النمو والرسوم المصغرة
        $kpis = $this->buildKpis($startDate, $endDate, $prevStartDate, $prevEndDate, $filters);

        // 2. بيانات الرسوم البيانية الستة (6 Charts)
        $charts = [
            'sales_trend'         => $this->buildSalesTrendChart($startDate, $endDate, $filters),
            'sales_by_channel'    => $this->buildSalesByChannelChart($startDate, $endDate, $filters),
            'payment_methods'     => $this->buildPaymentMethodsChart($startDate, $endDate, $filters),
            'top_products'        => $this->buildTopProductsChart($startDate, $endDate, $filters),
            'top_customers'       => $this->buildTopCustomersChart($startDate, $endDate, $filters),
            'cashier_performance' => $this->buildCashierPerformanceChart($startDate, $endDate, $filters),
        ];

        // 3. المراقبة الحية لورديات الكاشير (POS Shifts)
        $posShifts = $this->buildPosShiftsSummary($filters);

        // 4. التنبيهات التشغيلية وأحدث الفواتير
        $alertsAndRecent = $this->buildAlertsAndRecentInvoices($filters);

        return response()->json([
            'status' => true,
            'data'   => [
                'meta' => [
                    'date_from'     => $startDate->format('Y-m-d'),
                    'date_to'       => $endDate->format('Y-m-d'),
                    'branch_id'     => $filters['branch_id'],
                    'warehouse_id'  => $filters['warehouse_id'],
                    'channel'       => $filters['channel'],
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
                'kpis'            => $kpis,
                'charts'          => $charts,
                'pos_shifts'      => $posShifts,
                'alerts'          => $alertsAndRecent['alerts'],
                'recent_invoices' => $alertsAndRecent['recent_invoices'],
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
                'branch_id'    => isset($validated['branch_id']) ? (int) $validated['branch_id'] : null,
                'warehouse_id' => isset($validated['warehouse_id']) ? (int) $validated['warehouse_id'] : null,
                'channel'      => ! empty($validated['channel']) ? (string) $validated['channel'] : null,
            ],
        ];
    }

    /**
     * بناء بطاقات المؤشرات الرئيسية (KPIs) ونسب النمو والـ Sparklines
     *
     * @param array<string, mixed> $filters
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

        // الذمم الآجلة التراكمية غير المحصلة من الفواتير المؤكدة
        $receivablesQuery = SalesInvoice::query()
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->where('remaining_amount', '>', 0);

        $this->applyInvoiceScopeFilters($receivablesQuery, $filters);

        $totalReceivables = (float) ($receivablesQuery->selectRaw(
            'COALESCE(SUM(remaining_amount * COALESCE(NULLIF(exchange_rate, 0), 1)), 0) as total'
        )->value('total') ?? 0.0);

        $overdueReceivables = (float) ((clone $receivablesQuery)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', Carbon::today()->toDateString())
            ->selectRaw('COALESCE(SUM(remaining_amount * COALESCE(NULLIF(exchange_rate, 0), 1)), 0) as total')
            ->value('total') ?? 0.0);

        // نقاط Sparkline لآخر 7 أيام من نهاية الفترة
        $sparklines = $this->buildDailySparklines($endDate, $filters);

        return [
            'gross_sales' => [
                'value'             => $current['gross_sales'],
                'previous_value'    => $previous['gross_sales'],
                'growth_percentage' => $this->calculateGrowth($current['gross_sales'], $previous['gross_sales']),
                'sparkline'         => $sparklines['gross_sales'],
            ],
            'total_returns' => [
                'value'             => $current['total_returns'],
                'previous_value'    => $previous['total_returns'],
                'return_rate'       => $current['return_rate'],
                'growth_percentage' => $this->calculateGrowth($current['total_returns'], $previous['total_returns']),
                'sparkline'         => $sparklines['total_returns'],
            ],
            'net_sales' => [
                'value'             => $current['net_sales'],
                'previous_value'    => $previous['net_sales'],
                'growth_percentage' => $this->calculateGrowth($current['net_sales'], $previous['net_sales']),
                'sparkline'         => $sparklines['net_sales'],
            ],
            'gross_profit' => [
                'value'             => $current['gross_profit'],
                'previous_value'    => $previous['gross_profit'],
                'profit_margin'     => $current['profit_margin'],
                'growth_percentage' => $this->calculateGrowth($current['gross_profit'], $previous['gross_profit']),
                'sparkline'         => $sparklines['gross_profit'],
            ],
            'collections' => [
                'value'             => $current['net_collections'],
                'previous_value'    => $previous['net_collections'],
                'growth_percentage' => $this->calculateGrowth($current['net_collections'], $previous['net_collections']),
            ],
            'receivables' => [
                'value'          => round($totalReceivables, 2),
                'overdue_amount' => round($overdueReceivables, 2),
            ],
            'invoices_count' => [
                'value'               => $current['invoices_count'],
                'previous_value'      => $previous['invoices_count'],
                'average_order_value' => $current['average_order_value'],
                'growth_percentage'   => $this->calculateGrowth((float) $current['invoices_count'], (float) $previous['invoices_count']),
            ],
        ];
    }

    /**
     * احتساب المقاييس المالية والتشغيلية لفترة زمنية محددة
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function calculatePeriodMetrics(Carbon $startDate, Carbon $endDate, array $filters): array
    {
        // 1. المبيعات المؤكدة (غير المسودة وغير الملغاة)
        $invoicesQuery = SalesInvoice::query()
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->whereBetween('invoice_date', [$startDate, $endDate]);

        $this->applyInvoiceScopeFilters($invoicesQuery, $filters);

        $invoiceStats = $invoicesQuery->selectRaw('
            COUNT(*) as invoices_count,
            COALESCE(SUM(total_amount * COALESCE(NULLIF(exchange_rate, 0), 1)), 0) as gross_sales,
            COALESCE(SUM((total_amount - vat_amount) * COALESCE(NULLIF(exchange_rate, 0), 1)), 0) as net_revenue_excl_vat
        ')->first();

        $invoicesCount = (int) ($invoiceStats->invoices_count ?? 0);
        $grossSales = (float) ($invoiceStats->gross_sales ?? 0.0);
        $salesExclVat = (float) ($invoiceStats->net_revenue_excl_vat ?? 0.0);

        // 2. المرتجعات المؤكدة
        $returnsQuery = SalesReturn::query()
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->whereBetween('return_date', [$startDate, $endDate]);

        $this->applyReturnScopeFilters($returnsQuery, $filters);

        $returnStats = $returnsQuery->selectRaw('
            COALESCE(SUM(total_amount * COALESCE(NULLIF(exchange_rate, 0), 1)), 0) as total_returns,
            COALESCE(SUM((total_amount - vat_amount) * COALESCE(NULLIF(exchange_rate, 0), 1)), 0) as returns_excl_vat
        ')->first();

        $totalReturns = (float) ($returnStats->total_returns ?? 0.0);
        $returnsExclVat = (float) ($returnStats->returns_excl_vat ?? 0.0);

        // 3. تكلفة البضاعة المباعة COGS وتكلفة المرتجعات السليمة (good)
        $salesCogsQuery = SalesInvoiceItem::query()
            ->join('sales_invoices', 'sales_invoice_items.sales_invoice_id', '=', 'sales_invoices.id')
            ->whereNull('sales_invoices.deleted_at')
            ->whereNull('sales_invoice_items.deleted_at')
            ->whereNotIn('sales_invoices.status', ['draft', 'cancelled'])
            ->whereBetween('sales_invoices.invoice_date', [$startDate, $endDate]);

        $this->applyInvoiceScopeFilters($salesCogsQuery, $filters, 'sales_invoices');

        $salesCogs = (float) ($salesCogsQuery->selectRaw(
            'COALESCE(SUM(sales_invoice_items.quantity * sales_invoice_items.cost_price * COALESCE(NULLIF(sales_invoices.exchange_rate, 0), 1)), 0) as cogs'
        )->value('cogs') ?? 0.0);

        $returnsCogsQuery = SalesReturnItem::query()
            ->join('sales_returns', 'sales_return_items.sales_return_id', '=', 'sales_returns.id')
            ->whereNull('sales_returns.deleted_at')
            ->whereNull('sales_return_items.deleted_at')
            ->whereNotIn('sales_returns.status', ['draft', 'cancelled'])
            ->where('sales_return_items.item_condition', 'good')
            ->whereBetween('sales_returns.return_date', [$startDate, $endDate]);

        $this->applyReturnScopeFilters($returnsCogsQuery, $filters, 'sales_returns');

        $returnsCogs = (float) ($returnsCogsQuery->selectRaw(
            'COALESCE(SUM(sales_return_items.quantity * sales_return_items.cost_price * COALESCE(NULLIF(sales_returns.exchange_rate, 0), 1)), 0) as cogs'
        )->value('cogs') ?? 0.0);

        // 4. التحصيلات الفعلية (مقبوضات الفواتير مطروحاً منها مستردات المرتجعات)
        $paymentsQuery = SalesInvoicePayment::query()
            ->join('sales_invoices', 'sales_invoice_payments.sales_invoice_id', '=', 'sales_invoices.id')
            ->whereNull('sales_invoices.deleted_at')
            ->whereNull('sales_invoice_payments.deleted_at')
            ->whereNotIn('sales_invoices.status', ['draft', 'cancelled'])
            ->whereBetween('sales_invoice_payments.payment_date', [$startDate, $endDate]);

        $this->applyInvoiceScopeFilters($paymentsQuery, $filters, 'sales_invoices');

        $collectedAmount = (float) ($paymentsQuery->selectRaw(
            'COALESCE(SUM(sales_invoice_payments.amount * COALESCE(NULLIF(sales_invoice_payments.exchange_rate, 0), 1)), 0) as total'
        )->value('total') ?? 0.0);

        $refundsQuery = SalesReturnPayment::query()
            ->join('sales_returns', 'sales_return_payments.sales_return_id', '=', 'sales_returns.id')
            ->whereNull('sales_returns.deleted_at')
            ->whereNull('sales_return_payments.deleted_at')
            ->whereNotIn('sales_returns.status', ['draft', 'cancelled'])
            ->whereBetween('sales_return_payments.payment_date', [$startDate, $endDate]);

        $this->applyReturnScopeFilters($refundsQuery, $filters, 'sales_returns');

        $refundedAmount = (float) ($refundsQuery->selectRaw(
            'COALESCE(SUM(sales_return_payments.amount * COALESCE(NULLIF(sales_return_payments.exchange_rate, 0), 1)), 0) as total'
        )->value('total') ?? 0.0);

        $netSales = round($grossSales - $totalReturns, 2);
        $netRevenueExclVat = $salesExclVat - $returnsExclVat;
        $netCogs = $salesCogs - $returnsCogs;
        $grossProfit = round($netRevenueExclVat - $netCogs, 2);
        $profitMargin = $netRevenueExclVat > 0 ? round(($grossProfit / $netRevenueExclVat) * 100, 2) : 0.0;
        $returnRate = $grossSales > 0 ? round(($totalReturns / $grossSales) * 100, 2) : 0.0;
        $averageOrderValue = $invoicesCount > 0 ? round($grossSales / $invoicesCount, 2) : 0.0;

        return [
            'invoices_count'      => $invoicesCount,
            'gross_sales'         => round($grossSales, 2),
            'total_returns'       => round($totalReturns, 2),
            'net_sales'           => $netSales,
            'gross_profit'        => $grossProfit,
            'profit_margin'       => $profitMargin,
            'return_rate'         => $returnRate,
            'average_order_value' => $averageOrderValue,
            'net_collections'     => round($collectedAmount - $refundedAmount, 2),
        ];
    }

    /**
     * Chart 1: المنحنى الزمني للمبيعات والمرتجعات وصافي المبيعات ومجمل الربح
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function buildSalesTrendChart(Carbon $startDate, Carbon $endDate, array $filters): array
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
                'label'        => $label,
                'sales'        => 0.0,
                'returns'      => 0.0,
                'rev_excl_vat' => 0.0,
                'cogs'         => 0.0,
            ];
        }

        // جلب الفواتير
        $invoicesQuery = SalesInvoice::query()
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->whereBetween('invoice_date', [$startDate, $endDate]);

        $this->applyInvoiceScopeFilters($invoicesQuery, $filters);

        $invoices = $invoicesQuery->select([
            'id',
            'invoice_date',
            'total_amount',
            'vat_amount',
            'exchange_rate',
        ])->get();

        foreach ($invoices as $inv) {
            $k = Carbon::parse($inv->invoice_date)->format($keyFormat);
            if (! isset($buckets[$k])) {
                continue;
            }
            $rate = (float) $inv->exchange_rate > 0 ? (float) $inv->exchange_rate : 1.0;
            $buckets[$k]['sales'] += ((float) $inv->total_amount * $rate);
            $buckets[$k]['rev_excl_vat'] += (((float) $inv->total_amount - (float) $inv->vat_amount) * $rate);
        }

        // جلب تكلفة الفواتير
        $cogsQuery = SalesInvoiceItem::query()
            ->join('sales_invoices', 'sales_invoice_items.sales_invoice_id', '=', 'sales_invoices.id')
            ->whereNull('sales_invoices.deleted_at')
            ->whereNull('sales_invoice_items.deleted_at')
            ->whereNotIn('sales_invoices.status', ['draft', 'cancelled'])
            ->whereBetween('sales_invoices.invoice_date', [$startDate, $endDate]);

        $this->applyInvoiceScopeFilters($cogsQuery, $filters, 'sales_invoices');

        $cogsRows = $cogsQuery->select([
            'sales_invoices.invoice_date',
            'sales_invoices.exchange_rate',
            'sales_invoice_items.quantity',
            'sales_invoice_items.cost_price',
        ])->get();

        foreach ($cogsRows as $row) {
            $k = Carbon::parse((string) $row->invoice_date)->format($keyFormat);
            if (! isset($buckets[$k])) {
                continue;
            }
            $rate = (float) $row->exchange_rate > 0 ? (float) $row->exchange_rate : 1.0;
            $buckets[$k]['cogs'] += ((float) $row->quantity * (float) $row->cost_price * $rate);
        }

        // جلب المرتجعات
        $returnsQuery = SalesReturn::query()
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->whereBetween('return_date', [$startDate, $endDate]);

        $this->applyReturnScopeFilters($returnsQuery, $filters);

        $returns = $returnsQuery->select([
            'return_date',
            'total_amount',
            'vat_amount',
            'exchange_rate',
        ])->get();

        foreach ($returns as $ret) {
            $k = Carbon::parse($ret->return_date)->format($keyFormat);
            if (! isset($buckets[$k])) {
                continue;
            }
            $rate = (float) $ret->exchange_rate > 0 ? (float) $ret->exchange_rate : 1.0;
            $buckets[$k]['returns'] += ((float) $ret->total_amount * $rate);
            $buckets[$k]['rev_excl_vat'] -= (((float) $ret->total_amount - (float) $ret->vat_amount) * $rate);
        }

        $labels = [];
        $salesSeries = [];
        $returnsSeries = [];
        $netSalesSeries = [];
        $profitSeries = [];

        foreach ($buckets as $b) {
            $s = round($b['sales'], 2);
            $r = round($b['returns'], 2);
            $labels[] = $b['label'];
            $salesSeries[] = $s;
            $returnsSeries[] = $r;
            $netSalesSeries[] = round($s - $r, 2);
            $profitSeries[] = round($b['rev_excl_vat'] - $b['cogs'], 2);
        }

        return [
            'granularity' => $isMonthly ? 'monthly' : 'daily',
            'labels'      => $labels,
            'sales'       => $salesSeries,
            'returns'     => $returnsSeries,
            'net_sales'   => $netSalesSeries,
            'profit'      => $profitSeries,
        ];
    }

    /**
     * Chart 2: توزيع المبيعات حسب قناة البيع (POS / Standard / ...) ونوع الطلب
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function buildSalesByChannelChart(Carbon $startDate, Carbon $endDate, array $filters): array
    {
        $channelLabels = [
            'pos'      => 'نقاط البيع (POS)',
            'standard' => 'مبيعات الفواتير المباشرة',
            'online'   => 'المتجر الإلكتروني',
            'whatsapp' => 'طلبات واتساب',
            'delivery' => 'التوصيل',
        ];

        $query = SalesInvoice::query()
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->whereBetween('invoice_date', [$startDate, $endDate]);

        $this->applyInvoiceScopeFilters($query, $filters);

        $rows = $query->selectRaw('
                COALESCE(channel, \'standard\') as channel_key,
                COUNT(*) as invoices_count,
                COALESCE(SUM(total_amount * COALESCE(NULLIF(exchange_rate, 0), 1)), 0) as total_amount
            ')
            ->groupBy('channel_key')
            ->orderByDesc('total_amount')
            ->get();

        $items = $rows->map(function ($row) use ($channelLabels) {
            $key = (string) $row->channel_key;

            return [
                'channel'        => $key,
                'label'          => $channelLabels[$key] ?? $key,
                'invoices_count' => (int) $row->invoices_count,
                'amount'         => round((float) $row->total_amount, 2),
            ];
        })->values();

        return [
            'labels' => $items->pluck('label')->toArray(),
            'series' => $items->pluck('amount')->toArray(),
            'counts' => $items->pluck('invoices_count')->toArray(),
            'items'  => $items->toArray(),
        ];
    }

    /**
     * Chart 3: توزيع التحصيلات حسب وسيلة الدفع (نقدي، شبكة، تحويل بنكي...)
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function buildPaymentMethodsChart(Carbon $startDate, Carbon $endDate, array $filters): array
    {
        $methodLabels = [
            'cash'          => 'نقدي (كاش)',
            'card'          => 'بطاقة / شبكة (POS)',
            'bank_transfer' => 'تحويل بنكي',
            'cheque'        => 'شيك',
            'wallet'        => 'محفظة إلكترونية',
        ];

        $query = SalesInvoicePayment::query()
            ->join('sales_invoices', 'sales_invoice_payments.sales_invoice_id', '=', 'sales_invoices.id')
            ->whereNull('sales_invoices.deleted_at')
            ->whereNull('sales_invoice_payments.deleted_at')
            ->whereNotIn('sales_invoices.status', ['draft', 'cancelled'])
            ->whereBetween('sales_invoice_payments.payment_date', [$startDate, $endDate]);

        $this->applyInvoiceScopeFilters($query, $filters, 'sales_invoices');

        $rows = $query->selectRaw('
                COALESCE(sales_invoice_payments.payment_method, \'cash\') as method_key,
                COUNT(*) as transactions_count,
                COALESCE(SUM(sales_invoice_payments.amount * COALESCE(NULLIF(sales_invoice_payments.exchange_rate, 0), 1)), 0) as total_amount
            ')
            ->groupBy('method_key')
            ->orderByDesc('total_amount')
            ->get();

        $items = $rows->map(function ($row) use ($methodLabels) {
            $key = (string) $row->method_key;

            return [
                'method'             => $key,
                'label'              => $methodLabels[$key] ?? $key,
                'transactions_count' => (int) $row->transactions_count,
                'amount'             => round((float) $row->total_amount, 2),
            ];
        })->values();

        return [
            'labels' => $items->pluck('label')->toArray(),
            'series' => $items->pluck('amount')->toArray(),
            'counts' => $items->pluck('transactions_count')->toArray(),
            'items'  => $items->toArray(),
        ];
    }

    /**
     * Chart 4: أعلى 6 أصناف مبيعاً وربحية خلال الفترة
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function buildTopProductsChart(Carbon $startDate, Carbon $endDate, array $filters): array
    {
        $query = SalesInvoiceItem::query()
            ->join('sales_invoices', 'sales_invoice_items.sales_invoice_id', '=', 'sales_invoices.id')
            ->join('products', 'sales_invoice_items.product_id', '=', 'products.id')
            ->whereNull('sales_invoices.deleted_at')
            ->whereNull('sales_invoice_items.deleted_at')
            ->whereNotIn('sales_invoices.status', ['draft', 'cancelled'])
            ->whereBetween('sales_invoices.invoice_date', [$startDate, $endDate]);

        $this->applyInvoiceScopeFilters($query, $filters, 'sales_invoices');

        $rows = $query->selectRaw('
                products.id as product_id,
                products.name as product_name,
                COALESCE(SUM(sales_invoice_items.quantity), 0) as total_qty,
                COALESCE(SUM(sales_invoice_items.total_amount * COALESCE(NULLIF(sales_invoices.exchange_rate, 0), 1)), 0) as total_revenue,
                COALESCE(SUM((sales_invoice_items.subtotal - (sales_invoice_items.quantity * sales_invoice_items.cost_price)) * COALESCE(NULLIF(sales_invoices.exchange_rate, 0), 1)), 0) as total_profit
            ')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_revenue')
            ->limit(6)
            ->get();

        $items = $rows->map(function ($row) {
            $rev = round((float) $row->total_revenue, 2);
            $profit = round((float) $row->total_profit, 2);
            $margin = $rev > 0 ? round(($profit / $rev) * 100, 1) : 0.0;

            return [
                'product_id'   => (int) $row->product_id,
                'product_name' => $row->product_name,
                'quantity'     => round((float) $row->total_qty, 2),
                'revenue'      => $rev,
                'profit'       => $profit,
                'margin'       => $margin,
            ];
        })->values();

        return [
            'labels'         => $items->pluck('product_name')->toArray(),
            'revenue_series' => $items->pluck('revenue')->toArray(),
            'profit_series'  => $items->pluck('profit')->toArray(),
            'items'          => $items->toArray(),
        ];
    }

    /**
     * Chart 5: أعلى 6 عملاء شراءً خلال الفترة
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function buildTopCustomersChart(Carbon $startDate, Carbon $endDate, array $filters): array
    {
        $query = SalesInvoice::query()
            ->join('partners', 'sales_invoices.partner_id', '=', 'partners.id')
            ->whereNull('sales_invoices.deleted_at')
            ->whereNotIn('sales_invoices.status', ['draft', 'cancelled'])
            ->whereBetween('sales_invoices.invoice_date', [$startDate, $endDate]);

        $this->applyInvoiceScopeFilters($query, $filters, 'sales_invoices');

        $rows = $query->selectRaw('
                partners.id as partner_id,
                partners.name as partner_name,
                partners.phone as partner_phone,
                partners.credit_limit as credit_limit,
                COUNT(sales_invoices.id) as invoices_count,
                COALESCE(SUM(sales_invoices.total_amount * COALESCE(NULLIF(sales_invoices.exchange_rate, 0), 1)), 0) as total_purchases,
                COALESCE(SUM(sales_invoices.remaining_amount * COALESCE(NULLIF(sales_invoices.exchange_rate, 0), 1)), 0) as unpaid_balance
            ')
            ->groupBy('partners.id', 'partners.name', 'partners.phone', 'partners.credit_limit')
            ->orderByDesc('total_purchases')
            ->limit(6)
            ->get();

        $items = $rows->map(function ($row) {
            return [
                'partner_id'      => (int) $row->partner_id,
                'name'            => $row->partner_name,
                'phone'           => $row->partner_phone,
                'invoices_count'  => (int) $row->invoices_count,
                'total_purchases' => round((float) $row->total_purchases, 2),
                'unpaid_balance'  => round((float) $row->unpaid_balance, 2),
                'credit_limit'    => round((float) ($row->credit_limit ?? 0.0), 2),
            ];
        })->values();

        return [
            'labels' => $items->pluck('name')->toArray(),
            'series' => $items->pluck('total_purchases')->toArray(),
            'items'  => $items->toArray(),
        ];
    }

    /**
     * Chart 6: أداء الموظفين / الكاشيرين خلال الفترة
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function buildCashierPerformanceChart(Carbon $startDate, Carbon $endDate, array $filters): array
    {
        $query = SalesInvoice::query()
            ->join('users', 'sales_invoices.created_by', '=', 'users.id')
            ->whereNull('sales_invoices.deleted_at')
            ->whereNotIn('sales_invoices.status', ['draft', 'cancelled'])
            ->whereBetween('sales_invoices.invoice_date', [$startDate, $endDate]);

        $this->applyInvoiceScopeFilters($query, $filters, 'sales_invoices');

        $rows = $query->selectRaw('
                users.id as user_id,
                users.full_name as user_name,
                COUNT(sales_invoices.id) as invoices_count,
                COALESCE(SUM(sales_invoices.total_amount * COALESCE(NULLIF(sales_invoices.exchange_rate, 0), 1)), 0) as total_sales
            ')
            ->groupBy('users.id', 'users.full_name')
            ->orderByDesc('total_sales')
            ->limit(6)
            ->get();

        $items = $rows->map(function ($row) {
            return [
                'user_id'        => (int) $row->user_id,
                'name'           => $row->user_name,
                'invoices_count' => (int) $row->invoices_count,
                'total_sales'    => round((float) $row->total_sales, 2),
            ];
        })->values();

        return [
            'labels'          => $items->pluck('name')->toArray(),
            'sales_series'    => $items->pluck('total_sales')->toArray(),
            'invoices_series' => $items->pluck('invoices_count')->toArray(),
            'items'           => $items->toArray(),
        ];
    }

    /**
     * المراقبة الحية لورديات الكاشير المفتوحة والمغلقة حديثاً
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function buildPosShiftsSummary(array $filters): array
    {
        $openQuery = PosShift::query()
            ->where('status', 'open')
            ->with(['user:id,full_name', 'warehouse:id,name'])
            ->withCount(['invoices as active_invoices_count' => fn (Builder $q) => $q->where('status', '!=', 'cancelled')])
            ->withSum(['invoices as live_sales' => fn (Builder $q) => $q->where('status', '!=', 'cancelled')], 'total_amount')
            ->withSum(['returns as live_returns' => fn (Builder $q) => $q->where('status', '!=', 'cancelled')], 'total_amount');

        if ($filters['branch_id'] !== null) {
            $openQuery->where('branch_id', $filters['branch_id']);
        }
        if ($filters['warehouse_id'] !== null) {
            $openQuery->where('warehouse_id', $filters['warehouse_id']);
        }

        $openShifts = $openQuery->orderByDesc('opened_at')->get()->map(function (PosShift $shift) {
            $liveSales = round((float) ($shift->live_sales ?? 0.0), 2);
            $liveReturns = round((float) ($shift->live_returns ?? 0.0), 2);

            return [
                'id'             => $shift->id,
                'shift_number'   => $shift->shift_number,
                'cashier_name'   => $shift->user?->full_name,
                'warehouse_name' => $shift->warehouse?->name,
                'opened_at'      => $shift->opened_at?->format('Y-m-d H:i'),
                'opening_cash'   => round((float) $shift->opening_cash, 2),
                'invoices_count' => (int) ($shift->active_invoices_count ?? 0),
                'live_sales'     => $liveSales,
                'live_returns'   => $liveReturns,
                'net_sales'      => round($liveSales - $liveReturns, 2),
            ];
        })->values()->toArray();

        $closedQuery = PosShift::query()
            ->where('status', 'closed')
            ->with(['user:id,full_name', 'closedBy:id,full_name']);

        if ($filters['branch_id'] !== null) {
            $closedQuery->where('branch_id', $filters['branch_id']);
        }
        if ($filters['warehouse_id'] !== null) {
            $closedQuery->where('warehouse_id', $filters['warehouse_id']);
        }

        $recentClosedShifts = $closedQuery->orderByDesc('closed_at')
            ->limit(5)
            ->get()
            ->map(function (PosShift $shift) {
                return [
                    'id'            => $shift->id,
                    'shift_number'  => $shift->shift_number,
                    'cashier_name'  => $shift->user?->full_name,
                    'closed_by'     => $shift->closedBy?->full_name,
                    'closed_at'     => $shift->closed_at?->format('Y-m-d H:i'),
                    'total_sales'   => round((float) $shift->total_sales, 2),
                    'closing_cash'  => round((float) ($shift->closing_cash ?? 0.0), 2),
                    'actual_cash'   => round((float) ($shift->actual_cash ?? 0.0), 2),
                    'difference'    => round((float) $shift->difference, 2),
                ];
            })->values()->toArray();

        return [
            'open_shifts_count'    => count($openShifts),
            'open_shifts'          => $openShifts,
            'recent_closed_shifts' => $recentClosedShifts,
        ];
    }

    /**
     * التنبيهات التشغيلية وأحدث 8 فواتير مبيعات
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function buildAlertsAndRecentInvoices(array $filters): array
    {
        $draftInvoicesQuery = SalesInvoice::where('status', 'draft');
        $this->applyInvoiceScopeFilters($draftInvoicesQuery, $filters);

        $unpostedInvoicesQuery = SalesInvoice::where('status', 'confirmed')
            ->where('posting_status', 'unposted');
        $this->applyInvoiceScopeFilters($unpostedInvoicesQuery, $filters);

        $overdueInvoicesQuery = SalesInvoice::whereNotIn('status', ['draft', 'cancelled'])
            ->where('remaining_amount', '>', 0)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', Carbon::today()->toDateString());
        $this->applyInvoiceScopeFilters($overdueInvoicesQuery, $filters);

        $unpostedReturnsQuery = SalesReturn::where('status', '!=', 'cancelled')
            ->where('posting_status', 'unposted');
        $this->applyReturnScopeFilters($unpostedReturnsQuery, $filters);

        $varianceShiftsQuery = PosShift::where('status', 'closed')
            ->whereDate('closed_at', '>=', Carbon::today()->subDays(7)->toDateString())
            ->whereRaw('ABS(difference) > 0.01');

        if ($filters['branch_id'] !== null) {
            $varianceShiftsQuery->where('branch_id', $filters['branch_id']);
        }
        if ($filters['warehouse_id'] !== null) {
            $varianceShiftsQuery->where('warehouse_id', $filters['warehouse_id']);
        }

        $recentQuery = SalesInvoice::query()
            ->with(['partner:id,name', 'creator:id,full_name', 'currency:id,code,symbol']);
        $this->applyInvoiceScopeFilters($recentQuery, $filters);

        $recentInvoices = $recentQuery->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(function (SalesInvoice $invoice) {
                $customerName = $invoice->customer_name_text
                    ?: ($invoice->partner?->name ?? 'عميل نقدي');

                return [
                    'id'              => $invoice->id,
                    'invoice_number'  => $invoice->invoice_number,
                    'invoice_date'    => $invoice->invoice_date?->format('Y-m-d H:i'),
                    'customer_name'   => $customerName,
                    'channel'         => $invoice->channel ?? 'standard',
                    'status'          => $invoice->status,
                    'payment_status'  => $invoice->payment_status,
                    'posting_status'  => $invoice->posting_status,
                    'total_amount'    => round((float) $invoice->total_amount, 2),
                    'paid_amount'     => round((float) $invoice->paid_amount, 2),
                    'remaining_amount'=> round((float) $invoice->remaining_amount, 2),
                    'currency_symbol' => $invoice->currency?->symbol ?? '',
                    'cashier_name'    => $invoice->creator?->full_name,
                ];
            })->values()->toArray();

        return [
            'alerts' => [
                'draft_invoices_count'       => $draftInvoicesQuery->count(),
                'unposted_invoices_count'    => $unpostedInvoicesQuery->count(),
                'overdue_invoices_count'     => $overdueInvoicesQuery->count(),
                'unposted_returns_count'     => $unpostedReturnsQuery->count(),
                'shifts_with_variance_count' => $varianceShiftsQuery->count(),
            ],
            'recent_invoices' => $recentInvoices,
        ];
    }

    /**
     * بناء نقاط Sparkline اليومية لآخر 7 أيام
     *
     * @param array<string, mixed> $filters
     * @return array<string, array<int, float>>
     */
    private function buildDailySparklines(Carbon $endDate, array $filters): array
    {
        $start7D = $endDate->copy()->subDays(6)->startOfDay();
        $trend = $this->buildSalesTrendChart($start7D, $endDate, $filters);

        return [
            'gross_sales'   => $trend['sales'],
            'total_returns' => $trend['returns'],
            'net_sales'     => $trend['net_sales'],
            'gross_profit'  => $trend['profit'],
        ];
    }

    /**
     * تطبيق فلاتر النطاق (الفرع، المستودع، القناة) على استعلامات الفواتير
     *
     * @param Builder $query
     * @param array<string, mixed> $filters
     */
    private function applyInvoiceScopeFilters(Builder $query, array $filters, string $table = ''): void
    {
        $prefix = $table !== '' ? "{$table}." : '';

        if ($filters['branch_id'] !== null) {
            $query->where("{$prefix}branch_id", $filters['branch_id']);
        }
        if ($filters['warehouse_id'] !== null) {
            $query->where("{$prefix}warehouse_id", $filters['warehouse_id']);
        }
        if ($filters['channel'] !== null) {
            $query->where("{$prefix}channel", $filters['channel']);
        }
    }

    /**
     * تطبيق فلاتر النطاق (الفرع، المستودع) على استعلامات المرتجعات
     *
     * @param Builder $query
     * @param array<string, mixed> $filters
     */
    private function applyReturnScopeFilters(Builder $query, array $filters, string $table = ''): void
    {
        $prefix = $table !== '' ? "{$table}." : '';

        if ($filters['branch_id'] !== null) {
            $query->where("{$prefix}branch_id", $filters['branch_id']);
        }
        if ($filters['warehouse_id'] !== null) {
            $query->where("{$prefix}warehouse_id", $filters['warehouse_id']);
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