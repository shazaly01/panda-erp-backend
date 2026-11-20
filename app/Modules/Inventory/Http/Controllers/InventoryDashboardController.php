<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Inventory\Enums\StockSerialStatus;
use App\Modules\Inventory\Models\Adjustment;
use App\Modules\Inventory\Models\AdjustmentItem;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Models\ReorderRule;
use App\Modules\Inventory\Models\StockBatch;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockSerial;
use App\Modules\Inventory\Models\Transfer;
use App\Modules\Inventory\Models\Warehouse;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryDashboardController extends Controller
{
    /**
     * أنواع الحركات المخزنية المصنفة كوارد (إضافة)
     *
     * @var array<int, string>
     */
    private const INFLOW_TYPES = [
        'in',
        'transfer_in',
        'adjustment_in',
        'production_in',
        'purchase',
        'opening_balance',
    ];

    /**
     * أنواع الحركات المصنفة كمنصرف (خصم)
     *
     * @var array<int, string>
     */
    private const OUTFLOW_TYPES = [
        'out',
        'transfer_out',
        'adjustment_out',
        'production_out',
        'sales',
        'purchase_return',
        'damage',
        'loss',
    ];

    /**
     * عرض البيانات التحليلية والإحصائية الشاملة للوحة تحكم موديول المخازن والمستودعات
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! $user->can('inventory.dashboard.view')) {
            return response()->json([
                'message' => 'عذراً، ليس لديك صلاحية الوصول إلى لوحة تحكم المخازن.',
            ], 403);
        }

        $validated = $request->validate([
            'date_from'    => ['nullable', 'date'],
            'date_to'      => ['nullable', 'date', 'after_or_equal:date_from'],
            'warehouse_id' => ['nullable', 'integer', 'min:1'],
            'category_id'  => ['nullable', 'integer', 'min:1'],
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
            'movements_trend'        => $this->buildMovementsTrendChart($startDate, $endDate, $filters),
            'valuation_by_warehouse' => $this->buildValuationByWarehouseChart($filters),
            'valuation_by_category'  => $this->buildValuationByCategoryChart($filters),
            'movements_by_type'      => $this->buildMovementsByTypeChart($startDate, $endDate, $filters),
            'serials_by_status'      => $this->buildSerialsByStatusChart($filters),
            'top_moved_products'     => $this->buildTopMovedProductsChart($startDate, $endDate, $filters),
        ];

        // 3. التنبيهات الرقابية والقوائم التشغيلية السريعة
        $monitoring = $this->buildAlertsAndLists($filters);

        return response()->json([
            'status' => true,
            'data'   => [
                'meta' => [
                    'date_from'     => $startDate->format('Y-m-d'),
                    'date_to'       => $endDate->format('Y-m-d'),
                    'warehouse_id'  => $filters['warehouse_id'],
                    'category_id'   => $filters['category_id'],
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
                'kpis'                => $kpis,
                'charts'              => $charts,
                'alerts'              => $monitoring['alerts'],
                'reorder_alerts_list' => $monitoring['reorder_alerts_list'],
                'expiring_batches'    => $monitoring['expiring_batches'],
                'recent_movements'    => $monitoring['recent_movements'],
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
                'warehouse_id' => isset($validated['warehouse_id']) ? (int) $validated['warehouse_id'] : null,
                'category_id'  => isset($validated['category_id']) ? (int) $validated['category_id'] : null,
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
        // 1. تقييم المخزون الحي والكميات المتاحة والمحجوزة
        $stockQuery = ProductStock::query()
            ->join('inventory_products', 'inventory_product_stocks.product_id', '=', 'inventory_products.id')
            ->whereNull('inventory_product_stocks.deleted_at')
            ->whereNull('inventory_products.deleted_at');

        if ($filters['warehouse_id'] !== null) {
            $stockQuery->where('inventory_product_stocks.warehouse_id', $filters['warehouse_id']);
        }
        if ($filters['category_id'] !== null) {
            $stockQuery->where('inventory_products.category_id', $filters['category_id']);
        }

        $stockStats = $stockQuery->selectRaw('
            COALESCE(SUM(inventory_product_stocks.quantity), 0) as total_qty,
            COALESCE(SUM(inventory_product_stocks.reserved_quantity), 0) as reserved_qty,
            COALESCE(SUM(inventory_product_stocks.quantity - inventory_product_stocks.reserved_quantity), 0) as available_qty,
            COALESCE(SUM(inventory_product_stocks.quantity * inventory_products.cost_price), 0) as total_valuation
        ')->first();

        $totalQty = (float) ($stockStats->total_qty ?? 0.0);
        $reservedQty = (float) ($stockStats->reserved_qty ?? 0.0);
        $availableQty = (float) ($stockStats->available_qty ?? 0.0);
        $totalValuation = (float) ($stockStats->total_valuation ?? 0.0);

        $activeProductsCount = Product::where('is_active', true)
            ->when($filters['category_id'] !== null, fn (Builder $q) => $q->where('category_id', $filters['category_id']))
            ->count();

        $warehousesCount = Warehouse::where('is_active', true)
            ->when($filters['warehouse_id'] !== null, fn (Builder $q) => $q->where('id', $filters['warehouse_id']))
            ->count();

        // 2. حركات الوارد والمنصرف والتسويات للفترة الحالية والسابقة
        $currentMovements = $this->calculateMovementMetrics($startDate, $endDate, $filters);
        $previousMovements = $this->calculateMovementMetrics($prevStartDate, $prevEndDate, $filters);

        // 3. التسويات الجردية والتالف والفاقد المعتمدة خلال الفترة
        $adjustmentsMetrics = $this->calculateAdjustmentMetrics($startDate, $endDate, $filters);

        // 4. نواقص حد إعادة الطلب النشطة
        $reorderAlertsData = $this->evaluateReorderRules($filters);

        // 5. ملخص أسطوانات الغاز والأوزان الحية
        $serialsQuery = StockSerial::query()
            ->when($filters['warehouse_id'] !== null, fn (Builder $q) => $q->where('warehouse_id', $filters['warehouse_id']))
            ->when($filters['category_id'] !== null, function (Builder $q) use ($filters): void {
                $q->whereHas('product', fn (Builder $pq) => $pq->where('category_id', $filters['category_id']));
            });

        $serialRows = $serialsQuery->selectRaw('
                status,
                COUNT(*) as cnt,
                COALESCE(SUM(current_gas_weight), 0) as gas_weight
            ')
            ->groupBy('status')
            ->get();

        $cylinderCounts = [
            StockSerialStatus::IN_STOCK->value    => 0,
            StockSerialStatus::IN_SERVICE->value  => 0,
            StockSerialStatus::EMPTY->value       => 0,
            StockSerialStatus::WITH_VENDOR->value => 0,
        ];
        $activeGasWeightKg = 0.0;

        foreach ($serialRows as $row) {
            $statusVal = $row->status instanceof StockSerialStatus ? $row->status->value : (string) $row->status;
            if (array_key_exists($statusVal, $cylinderCounts)) {
                $cylinderCounts[$statusVal] = (int) $row->cnt;
            }
            if (in_array($statusVal, [StockSerialStatus::IN_STOCK->value, StockSerialStatus::IN_SERVICE->value], true)) {
                $activeGasWeightKg += (float) $row->gas_weight;
            }
        }

        $sparklines = $this->buildDailySparklines($endDate, $filters);

        return [
            'total_valuation' => [
                'value'                 => round($totalValuation, 2),
                'active_products_count' => $activeProductsCount,
                'warehouses_count'      => $warehousesCount,
            ],
            'stock_quantities' => [
                'total_quantity'     => round($totalQty, 2),
                'reserved_quantity'  => round($reservedQty, 2),
                'available_quantity' => round($availableQty, 2),
            ],
            'inflow_value' => [
                'value'             => $currentMovements['inflow_cost'],
                'previous_value'    => $previousMovements['inflow_cost'],
                'movements_count'   => $currentMovements['inflow_count'],
                'growth_percentage' => $this->calculateGrowth($currentMovements['inflow_cost'], $previousMovements['inflow_cost']),
                'sparkline'         => $sparklines['inflow'],
            ],
            'outflow_value' => [
                'value'             => $currentMovements['outflow_cost'],
                'previous_value'    => $previousMovements['outflow_cost'],
                'movements_count'   => $currentMovements['outflow_count'],
                'growth_percentage' => $this->calculateGrowth($currentMovements['outflow_cost'], $previousMovements['outflow_cost']),
                'sparkline'         => $sparklines['outflow'],
            ],
            'adjustments_variance' => [
                'net_variance_cost'    => $adjustmentsMetrics['net_variance_cost'],
                'surplus_cost'         => $adjustmentsMetrics['surplus_cost'],
                'deficit_cost'         => $adjustmentsMetrics['deficit_cost'],
                'damage_and_loss_cost' => $adjustmentsMetrics['damage_and_loss_cost'],
            ],
            'reorder_alerts' => [
                'count' => count($reorderAlertsData),
            ],
            'gas_cylinders' => [
                'total_gas_weight_kg' => round($activeGasWeightKg, 2),
                'in_stock_count'      => $cylinderCounts[StockSerialStatus::IN_STOCK->value],
                'in_service_count'    => $cylinderCounts[StockSerialStatus::IN_SERVICE->value],
                'empty_count'         => $cylinderCounts[StockSerialStatus::EMPTY->value],
                'with_vendor_count'   => $cylinderCounts[StockSerialStatus::WITH_VENDOR->value],
            ],
        ];
    }

    /**
     * احتساب إجماليات حركات الوارد والمنصرف خلال فترة محددة
     *
     * @param array<string, int|null> $filters
     * @return array<string, mixed>
     */
    private function calculateMovementMetrics(Carbon $startDate, Carbon $endDate, array $filters): array
    {
        $query = StockMovement::query()
            ->whereBetween('created_at', [$startDate, $endDate]);

        $this->applyMovementScopeFilters($query, $filters);

        $rows = $query->selectRaw('
                movement_type,
                COUNT(*) as cnt,
                COALESCE(SUM(total_cost), 0) as total_cost
            ')
            ->groupBy('movement_type')
            ->get();

        $inflowCost = 0.0;
        $inflowCount = 0;
        $outflowCost = 0.0;
        $outflowCount = 0;

        foreach ($rows as $row) {
            $type = (string) $row->movement_type;
            $cost = (float) $row->total_cost;
            $cnt = (int) $row->cnt;

            if (in_array($type, self::INFLOW_TYPES, true)) {
                $inflowCost += $cost;
                $inflowCount += $cnt;
            } elseif (in_array($type, self::OUTFLOW_TYPES, true)) {
                $outflowCost += $cost;
                $outflowCount += $cnt;
            }
        }

        return [
            'inflow_cost'   => round($inflowCost, 2),
            'inflow_count'  => $inflowCount,
            'outflow_cost'  => round($outflowCost, 2),
            'outflow_count' => $outflowCount,
        ];
    }

    /**
     * احتساب تكاليف وفروقات التسويات الجردية والتالف والفاقد المعتمدة خلال الفترة
     *
     * @param array<string, int|null> $filters
     * @return array<string, float>
     */
    private function calculateAdjustmentMetrics(Carbon $startDate, Carbon $endDate, array $filters): array
    {
        $query = AdjustmentItem::query()
            ->join('inventory_adjustments', 'inventory_adjustment_items.adjustment_id', '=', 'inventory_adjustments.id')
            ->join('inventory_products', 'inventory_adjustment_items.product_id', '=', 'inventory_products.id')
            ->whereNull('inventory_adjustments.deleted_at')
            ->whereNull('inventory_adjustment_items.deleted_at')
            ->where('inventory_adjustments.status', 'approved')
            ->where('inventory_adjustments.type', '!=', 'opening_balance')
            ->whereDate('inventory_adjustments.adjustment_date', '>=', $startDate->toDateString())
            ->whereDate('inventory_adjustments.adjustment_date', '<=', $endDate->toDateString());

        if ($filters['warehouse_id'] !== null) {
            $query->where('inventory_adjustments.warehouse_id', $filters['warehouse_id']);
        }
        if ($filters['category_id'] !== null) {
            $query->where('inventory_products.category_id', $filters['category_id']);
        }

        $rows = $query->select([
            'inventory_adjustments.type',
            'inventory_adjustment_items.quantity_difference',
            'inventory_adjustment_items.total_cost',
        ])->get();

        $surplusCost = 0.0;
        $deficitCost = 0.0;
        $damageAndLossCost = 0.0;

        foreach ($rows as $row) {
            $type = (string) $row->type;
            $diff = (float) $row->quantity_difference;
            $cost = abs((float) $row->total_cost);

            if (in_array($type, ['damage', 'loss'], true)) {
                $damageAndLossCost += $cost;
                $deficitCost += $cost;
            } elseif ($diff > 0) {
                $surplusCost += $cost;
            } elseif ($diff < 0) {
                $deficitCost += $cost;
            }
        }

        return [
            'surplus_cost'         => round($surplusCost, 2),
            'deficit_cost'         => round($deficitCost, 2),
            'damage_and_loss_cost' => round($damageAndLossCost, 2),
            'net_variance_cost'    => round($surplusCost - $deficitCost, 2),
        ];
    }

    /**
     * Chart 1: المنحنى الزمني لقيمة الوارد المخزني مقابل المنصرف وصافي الحركة
     *
     * @param array<string, int|null> $filters
     * @return array<string, mixed>
     */
    private function buildMovementsTrendChart(Carbon $startDate, Carbon $endDate, array $filters): array
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
                'label'   => $label,
                'inflow'  => 0.0,
                'outflow' => 0.0,
            ];
        }

        $query = StockMovement::query()
            ->whereBetween('created_at', [$startDate, $endDate]);

        $this->applyMovementScopeFilters($query, $filters);

        $movements = $query->select(['created_at', 'movement_type', 'total_cost'])->get();

        foreach ($movements as $mv) {
            $k = Carbon::parse($mv->created_at)->format($keyFormat);
            if (! isset($buckets[$k])) {
                continue;
            }
            $type = (string) $mv->movement_type;
            $cost = (float) $mv->total_cost;

            if (in_array($type, self::INFLOW_TYPES, true)) {
                $buckets[$k]['inflow'] += $cost;
            } elseif (in_array($type, self::OUTFLOW_TYPES, true)) {
                $buckets[$k]['outflow'] += $cost;
            }
        }

        $labels = [];
        $inflowSeries = [];
        $outflowSeries = [];
        $netSeries = [];

        foreach ($buckets as $b) {
            $in = round($b['inflow'], 2);
            $out = round($b['outflow'], 2);
            $labels[] = $b['label'];
            $inflowSeries[] = $in;
            $outflowSeries[] = $out;
            $netSeries[] = round($in - $out, 2);
        }

        return [
            'granularity' => $isMonthly ? 'monthly' : 'daily',
            'labels'      => $labels,
            'inflow'      => $inflowSeries,
            'outflow'     => $outflowSeries,
            'net'         => $netSeries,
        ];
    }

    /**
     * Chart 2: توزيع قيمة تقييم المخزون والكميات على المستودعات
     *
     * @param array<string, int|null> $filters
     * @return array<string, mixed>
     */
    private function buildValuationByWarehouseChart(array $filters): array
    {
        $query = ProductStock::query()
            ->join('inventory_warehouses', 'inventory_product_stocks.warehouse_id', '=', 'inventory_warehouses.id')
            ->join('inventory_products', 'inventory_product_stocks.product_id', '=', 'inventory_products.id')
            ->whereNull('inventory_product_stocks.deleted_at')
            ->whereNull('inventory_warehouses.deleted_at')
            ->whereNull('inventory_products.deleted_at');

        if ($filters['warehouse_id'] !== null) {
            $query->where('inventory_warehouses.id', $filters['warehouse_id']);
        }
        if ($filters['category_id'] !== null) {
            $query->where('inventory_products.category_id', $filters['category_id']);
        }

        $rows = $query->selectRaw('
                inventory_warehouses.id as warehouse_id,
                inventory_warehouses.name as warehouse_name,
                inventory_warehouses.code as warehouse_code,
                inventory_warehouses.is_operating as is_operating,
                COUNT(DISTINCT inventory_product_stocks.product_id) as products_count,
                COALESCE(SUM(inventory_product_stocks.quantity), 0) as total_quantity,
                COALESCE(SUM(inventory_product_stocks.quantity * inventory_products.cost_price), 0) as valuation
            ')
            ->groupBy(
                'inventory_warehouses.id',
                'inventory_warehouses.name',
                'inventory_warehouses.code',
                'inventory_warehouses.is_operating'
            )
            ->orderByDesc('valuation')
            ->get();

        $items = $rows->map(function ($row) {
            return [
                'warehouse_id'   => (int) $row->warehouse_id,
                'warehouse_name' => $row->warehouse_name,
                'warehouse_code' => $row->warehouse_code,
                'is_operating'   => (bool) $row->is_operating,
                'products_count' => (int) $row->products_count,
                'total_quantity' => round((float) $row->total_quantity, 2),
                'valuation'      => round((float) $row->valuation, 2),
            ];
        })->values();

        return [
            'labels'           => $items->pluck('warehouse_name')->toArray(),
            'valuation_series' => $items->pluck('valuation')->toArray(),
            'quantity_series'  => $items->pluck('total_quantity')->toArray(),
            'items'            => $items->toArray(),
        ];
    }

    /**
     * Chart 3: توزيع قيمة المخزون حسب التصنيفات
     *
     * @param array<string, int|null> $filters
     * @return array<string, mixed>
     */
    private function buildValuationByCategoryChart(array $filters): array
    {
        $query = ProductStock::query()
            ->join('inventory_products', 'inventory_product_stocks.product_id', '=', 'inventory_products.id')
            ->leftJoin('inventory_categories', 'inventory_products.category_id', '=', 'inventory_categories.id')
            ->whereNull('inventory_product_stocks.deleted_at')
            ->whereNull('inventory_products.deleted_at');

        if ($filters['warehouse_id'] !== null) {
            $query->where('inventory_product_stocks.warehouse_id', $filters['warehouse_id']);
        }
        if ($filters['category_id'] !== null) {
            $query->where('inventory_products.category_id', $filters['category_id']);
        }

        $rows = $query->selectRaw('
                inventory_products.category_id as category_id,
                COALESCE(inventory_categories.name, \'غير مصنف\') as category_name,
                COUNT(DISTINCT inventory_products.id) as products_count,
                COALESCE(SUM(inventory_product_stocks.quantity), 0) as total_quantity,
                COALESCE(SUM(inventory_product_stocks.quantity * inventory_products.cost_price), 0) as valuation
            ')
            ->groupBy('inventory_products.category_id', 'inventory_categories.name')
            ->orderByDesc('valuation')
            ->limit(8)
            ->get();

        $items = $rows->map(function ($row) {
            return [
                'category_id'    => $row->category_id ? (int) $row->category_id : null,
                'category_name'  => $row->category_name,
                'products_count' => (int) $row->products_count,
                'total_quantity' => round((float) $row->total_quantity, 2),
                'valuation'      => round((float) $row->valuation, 2),
            ];
        })->values();

        return [
            'labels' => $items->pluck('category_name')->toArray(),
            'series' => $items->pluck('valuation')->toArray(),
            'items'  => $items->toArray(),
        ];
    }

    /**
     * Chart 4: تحليل الحركات المخزنية حسب النوع خلال الفترة
     *
     * @param array<string, int|null> $filters
     * @return array<string, mixed>
     */
    private function buildMovementsByTypeChart(Carbon $startDate, Carbon $endDate, array $filters): array
    {
        $typeLabels = [
            'purchase'        => 'استلام مشتريات',
            'sales'           => 'منصرف مبيعات',
            'out'             => 'صرف تشغيل / أقسام',
            'in'              => 'إضافة مخزنية',
            'transfer_in'     => 'تحويل وارد',
            'transfer_out'    => 'تحويل صادر',
            'adjustment_in'   => 'تسوية جردية (فائض)',
            'adjustment_out'  => 'تسوية جردية (عجز)',
            'opening_balance' => 'رصيد افتتاحي',
            'production_in'   => 'إنتاج تام وارد',
            'production_out'  => 'خامات منصرفة للإنتاج',
            'purchase_return' => 'مردود مشتريات',
            'damage'          => 'إتلاف بضاعة',
            'loss'            => 'فاقد وتسريب',
        ];

        $query = StockMovement::query()
            ->whereBetween('created_at', [$startDate, $endDate]);

        $this->applyMovementScopeFilters($query, $filters);

        $rows = $query->selectRaw('
                movement_type,
                COUNT(*) as movements_count,
                COALESCE(SUM(total_cost), 0) as total_cost
            ')
            ->groupBy('movement_type')
            ->orderByDesc('total_cost')
            ->get();

        $items = $rows->map(function ($row) use ($typeLabels) {
            $type = (string) $row->movement_type;
            $direction = in_array($type, self::INFLOW_TYPES, true) ? 'in' : 'out';

            return [
                'movement_type'   => $type,
                'label'           => $typeLabels[$type] ?? $type,
                'direction'       => $direction,
                'movements_count' => (int) $row->movements_count,
                'total_cost'      => round((float) $row->total_cost, 2),
            ];
        })->values();

        return [
            'labels' => $items->pluck('label')->toArray(),
            'series' => $items->pluck('total_cost')->toArray(),
            'counts' => $items->pluck('movements_count')->toArray(),
            'items'  => $items->toArray(),
        ];
    }

    /**
     * Chart 5: التوزيع الحي لحالات الأرقام التسلسلية وأسطوانات الغاز وأوزانها
     *
     * @param array<string, int|null> $filters
     * @return array<string, mixed>
     */
    private function buildSerialsByStatusChart(array $filters): array
    {
        $query = StockSerial::query()
            ->when($filters['warehouse_id'] !== null, fn (Builder $q) => $q->where('warehouse_id', $filters['warehouse_id']))
            ->when($filters['category_id'] !== null, function (Builder $q) use ($filters): void {
                $q->whereHas('product', fn (Builder $pq) => $pq->where('category_id', $filters['category_id']));
            });

        $rows = $query->selectRaw('
                status,
                COUNT(*) as cnt,
                COALESCE(SUM(current_gas_weight), 0) as gas_weight
            ')
            ->groupBy('status')
            ->get()
            ->keyBy(fn ($r) => $r->status instanceof StockSerialStatus ? $r->status->value : (string) $r->status);

        $items = [];
        foreach (StockSerialStatus::cases() as $case) {
            $row = $rows->get($case->value);
            $items[] = [
                'status'        => $case->value,
                'label'         => $case->label(),
                'count'         => $row ? (int) $row->cnt : 0,
                'gas_weight_kg' => $row ? round((float) $row->gas_weight, 2) : 0.0,
            ];
        }

        return [
            'labels'         => array_column($items, 'label'),
            'count_series'   => array_column($items, 'count'),
            'weight_series'  => array_column($items, 'gas_weight_kg'),
            'items'          => $items,
        ];
    }

    /**
     * Chart 6: أعلى 6 أصناف حركةً واستهلاكاً (من حيث قيمة المنصرف) خلال الفترة
     *
     * @param array<string, int|null> $filters
     * @return array<string, mixed>
     */
    private function buildTopMovedProductsChart(Carbon $startDate, Carbon $endDate, array $filters): array
    {
        $query = StockMovement::query()
            ->join('inventory_products', 'inventory_stock_movements.product_id', '=', 'inventory_products.id')
            ->leftJoin('inventory_product_units', 'inventory_stock_movements.product_unit_id', '=', 'inventory_product_units.id')
            ->whereNull('inventory_stock_movements.deleted_at')
            ->whereNull('inventory_products.deleted_at')
            ->whereIn('inventory_stock_movements.movement_type', self::OUTFLOW_TYPES)
            ->whereBetween('inventory_stock_movements.created_at', [$startDate, $endDate]);

        if ($filters['warehouse_id'] !== null) {
            $query->where('inventory_stock_movements.warehouse_id', $filters['warehouse_id']);
        }
        if ($filters['category_id'] !== null) {
            $query->where('inventory_products.category_id', $filters['category_id']);
        }

        $rows = $query->selectRaw('
                inventory_products.id as product_id,
                inventory_products.name as product_name,
                inventory_products.sku as product_sku,
                COUNT(inventory_stock_movements.id) as movements_count,
                COALESCE(SUM(inventory_stock_movements.quantity * COALESCE(NULLIF(inventory_product_units.conversion_factor, 0), 1)), 0) as out_quantity,
                COALESCE(SUM(inventory_stock_movements.total_cost), 0) as out_cost
            ')
            ->groupBy('inventory_products.id', 'inventory_products.name', 'inventory_products.sku')
            ->orderByDesc('out_cost')
            ->limit(6)
            ->get();

        $productIds = $rows->pluck('product_id')->map(fn ($id) => (int) $id)->all();
        $currentStocks = ProductStock::query()
            ->whereIn('product_id', $productIds)
            ->when($filters['warehouse_id'] !== null, fn (Builder $q) => $q->where('warehouse_id', $filters['warehouse_id']))
            ->selectRaw('product_id, COALESCE(SUM(quantity - reserved_quantity), 0) as available_qty')
            ->groupBy('product_id')
            ->pluck('available_qty', 'product_id');

        $items = $rows->map(function ($row) use ($currentStocks) {
            $pid = (int) $row->product_id;

            return [
                'product_id'      => $pid,
                'product_name'    => $row->product_name,
                'sku'             => $row->product_sku,
                'movements_count' => (int) $row->movements_count,
                'out_quantity'    => round((float) $row->out_quantity, 2),
                'out_cost'        => round((float) $row->out_cost, 2),
                'available_stock' => round((float) ($currentStocks[$pid] ?? 0.0), 2),
            ];
        })->values();

        return [
            'labels'          => $items->pluck('product_name')->toArray(),
            'cost_series'     => $items->pluck('out_cost')->toArray(),
            'quantity_series' => $items->pluck('out_quantity')->toArray(),
            'items'           => $items->toArray(),
        ];
    }

    /**
     * بناء التنبيهات الرقابية والقوائم التشغيلية السريعة
     *
     * @param array<string, int|null> $filters
     * @return array<string, mixed>
     */
    private function buildAlertsAndLists(array $filters): array
    {
        // 1. نواقص حد إعادة الطلب
        $reorderList = $this->evaluateReorderRules($filters);

        // 2. الأرصدة السالبة
        $negativeStockCount = ProductStock::query()
            ->where('quantity', '<', 0)
            ->when($filters['warehouse_id'] !== null, fn (Builder $q) => $q->where('warehouse_id', $filters['warehouse_id']))
            ->when($filters['category_id'] !== null, function (Builder $q) use ($filters): void {
                $q->whereHas('product', fn (Builder $pq) => $pq->where('category_id', $filters['category_id']));
            })
            ->count();

        // 3. التحويلات المخزنية المعلقة
        $pendingTransfersCount = Transfer::query()
            ->where('status', 'pending')
            ->when($filters['warehouse_id'] !== null, function (Builder $q) use ($filters): void {
                $wId = $filters['warehouse_id'];
                $q->where(fn (Builder $sub) => $sub->where('from_warehouse_id', $wId)->orWhere('to_warehouse_id', $wId));
            })
            ->count();

        // 4. التسويات الجردية المسودة غير المعتمدة
        $draftAdjustmentsCount = Adjustment::query()
            ->where('status', 'draft')
            ->when($filters['warehouse_id'] !== null, fn (Builder $q) => $q->where('warehouse_id', $filters['warehouse_id']))
            ->count();

        // 5. التشغيلات المنتهية أو القريبة من الانتهاء خلال 60 يوماً
        $now = Carbon::today();
        $thresholdDate = $now->copy()->addDays(60);

        $batches = StockBatch::query()
            ->with(['product:id,name,sku,category_id', 'stocks' => function ($q) use ($filters): void {
                if ($filters['warehouse_id'] !== null) {
                    $q->where('warehouse_id', $filters['warehouse_id']);
                }
                $q->with('warehouse:id,name');
            }])
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', $thresholdDate->toDateString())
            ->when($filters['category_id'] !== null, function (Builder $q) use ($filters): void {
                $q->whereHas('product', fn (Builder $pq) => $pq->where('category_id', $filters['category_id']));
            })
            ->orderBy('expiry_date', 'asc')
            ->get();

        $expiredCount = 0;
        $nearExpiryCount = 0;
        $expiringBatchesList = [];

        foreach ($batches as $batch) {
            $stockQty = (float) $batch->stocks->sum('quantity');
            if ($stockQty <= 0) {
                continue;
            }

            $expiry = Carbon::parse($batch->expiry_date)->startOfDay();
            $daysRemaining = (int) $now->diffInDays($expiry, false);
            $isExpired = $daysRemaining < 0;

            if ($isExpired) {
                $expiredCount++;
            } else {
                $nearExpiryCount++;
            }

            if (count($expiringBatchesList) < 5) {
                $expiringBatchesList[] = [
                    'id'             => $batch->id,
                    'batch_number'   => $batch->batch_number,
                    'product_name'   => $batch->product?->name,
                    'sku'            => $batch->product?->sku,
                    'expiry_date'    => $expiry->format('Y-m-d'),
                    'days_remaining' => $daysRemaining,
                    'status'         => $isExpired ? 'EXPIRED' : 'NEAR_EXPIRY',
                    'current_stock'  => round($stockQty, 2),
                ];
            }
        }

        // 6. الأسطوانات الفارغة ولدى الموردين
        $emptyCylindersCount = StockSerial::where('status', StockSerialStatus::EMPTY->value)
            ->when($filters['warehouse_id'] !== null, fn (Builder $q) => $q->where('warehouse_id', $filters['warehouse_id']))
            ->count();

        $withVendorCylindersCount = StockSerial::where('status', StockSerialStatus::WITH_VENDOR->value)
            ->count();

        // 7. أحدث 8 حركات مخزنية مسجلة
        $recentMovementsQuery = StockMovement::query()
            ->with([
                'product:id,name,sku',
                'warehouse:id,name,code',
                'productUnit.unit:id,name',
                'creator:id,full_name',
            ]);

        $this->applyMovementScopeFilters($recentMovementsQuery, $filters);

        $recentMovements = $recentMovementsQuery->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(function (StockMovement $mv) {
                $type = (string) $mv->movement_type;
                $direction = in_array($type, self::INFLOW_TYPES, true) ? 'in' : 'out';

                return [
                    'id'                     => $mv->id,
                    'created_at'             => $mv->created_at?->format('Y-m-d H:i'),
                    'movement_type'          => $type,
                    'direction'              => $direction,
                    'product_name'           => $mv->product?->name,
                    'sku'                    => $mv->product?->sku,
                    'warehouse_name'         => $mv->warehouse?->name,
                    'unit_name'              => $mv->productUnit?->unit?->name,
                    'quantity'               => round((float) $mv->quantity, 2),
                    'unit_cost'              => round((float) $mv->unit_cost, 2),
                    'total_cost'             => round((float) $mv->total_cost, 2),
                    'balance_after_movement' => round((float) $mv->balance_after_movement, 2),
                    'created_by'             => $mv->creator?->full_name,
                    'notes'                  => $mv->notes,
                ];
            })->values()->toArray();

        return [
            'alerts' => [
                'reorder_alerts_count'        => count($reorderList),
                'negative_stock_count'        => $negativeStockCount,
                'pending_transfers_count'     => $pendingTransfersCount,
                'draft_adjustments_count'     => $draftAdjustmentsCount,
                'expired_batches_count'       => $expiredCount,
                'near_expiry_batches_count'   => $nearExpiryCount,
                'empty_cylinders_count'       => $emptyCylindersCount,
                'with_vendor_cylinders_count' => $withVendorCylindersCount,
            ],
            'reorder_alerts_list' => array_slice($reorderList, 0, 6),
            'expiring_batches'    => $expiringBatchesList,
            'recent_movements'    => $recentMovements,
        ];
    }

    /**
     * تقييم قواعد إعادة الطلب النشطة واستخراج الأصناف التي بلغت أو انخفضت عن الحد الأدنى
     *
     * @param array<string, int|null> $filters
     * @return array<int, array<string, mixed>>
     */
    private function evaluateReorderRules(array $filters): array
    {
        $rules = ReorderRule::query()
            ->with(['product:id,name,sku,category_id', 'warehouse:id,name'])
            ->where('is_active', true)
            ->when($filters['warehouse_id'] !== null, fn (Builder $q) => $q->where('warehouse_id', $filters['warehouse_id']))
            ->when($filters['category_id'] !== null, function (Builder $q) use ($filters): void {
                $q->whereHas('product', fn (Builder $pq) => $pq->where('category_id', $filters['category_id']));
            })
            ->get();

        if ($rules->isEmpty()) {
            return [];
        }

        $productIds = $rules->pluck('product_id')->unique()->all();
        $warehouseIds = $rules->pluck('warehouse_id')->unique()->all();

        $stockMap = ProductStock::query()
            ->whereIn('product_id', $productIds)
            ->whereIn('warehouse_id', $warehouseIds)
            ->selectRaw('product_id, warehouse_id, COALESCE(SUM(quantity - reserved_quantity), 0) as available_qty')
            ->groupBy('product_id', 'warehouse_id')
            ->get()
            ->keyBy(fn ($r) => "{$r->product_id}-{$r->warehouse_id}");

        $alerts = [];
        foreach ($rules as $rule) {
            $key = "{$rule->product_id}-{$rule->warehouse_id}";
            $available = (float) ($stockMap->get($key)?->available_qty ?? 0.0);
            $minQty = (float) $rule->min_quantity;
            $reorderQty = (float) $rule->reorder_quantity;

            if ($available <= $minQty) {
                $shortage = max(0.0, $minQty - $available);
                $alerts[] = [
                    'product_id'         => (int) $rule->product_id,
                    'product_name'       => $rule->product?->name,
                    'sku'                => $rule->product?->sku,
                    'warehouse_id'       => (int) $rule->warehouse_id,
                    'warehouse_name'     => $rule->warehouse?->name,
                    'available_quantity' => round($available, 2),
                    'min_quantity'       => round($minQty, 2),
                    'shortage_quantity'  => round($shortage, 2),
                    'suggested_order'    => round(max($reorderQty, $shortage), 2),
                ];
            }
        }

        usort($alerts, fn (array $a, array $b): int => $b['shortage_quantity'] <=> $a['shortage_quantity']);

        return $alerts;
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
        $trend = $this->buildMovementsTrendChart($start7D, $endDate, $filters);

        return [
            'inflow'  => $trend['inflow'],
            'outflow' => $trend['outflow'],
        ];
    }

    /**
     * تطبيق فلاتر النطاق (المستودع والتصنيف) على استعلامات الحركات المخزنية
     *
     * @param Builder $query
     * @param array<string, int|null> $filters
     */
    private function applyMovementScopeFilters(Builder $query, array $filters): void
    {
        if ($filters['warehouse_id'] !== null) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }
        if ($filters['category_id'] !== null) {
            $categoryId = $filters['category_id'];
            $query->whereHas('product', fn (Builder $q) => $q->where('category_id', $categoryId));
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