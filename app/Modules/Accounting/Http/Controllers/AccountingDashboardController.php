<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Accounting\Enums\BudgetStatus;
use App\Modules\Accounting\Enums\EntrySource;
use App\Modules\Accounting\Enums\EntryStatus;
use App\Modules\Accounting\Enums\FiscalYearStatus;
use App\Modules\Accounting\Enums\VoucherStatus;
use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Models\Box;
use App\Modules\Accounting\Models\Budget;
use App\Modules\Accounting\Models\CostCenter;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\JournalEntryDetail;
use App\Modules\Accounting\Models\Voucher;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class AccountingDashboardController extends Controller
{
    /**
     * عرض البيانات التحليلية والإحصائية الشاملة للوحة تحكم موديول المحاسبة
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! $user->can('dashboard.view')) {
            return response()->json([
                'message' => 'عذراً، ليس لديك صلاحية الوصول إلى لوحة تحكم المحاسبة.',
            ], 403);
        }

        $validated = $request->validate([
            'fiscal_year_id' => ['nullable', 'integer', 'exists:fiscal_years,id'],
            'branch_id'      => ['nullable', 'integer', 'exists:cost_centers,id'],
            'date_from'      => ['nullable', 'date'],
            'date_to'        => ['nullable', 'date', 'after_or_equal:date_from'],
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
        /** @var array<int, int>|null $costCenterIds */
        $costCenterIds = $scope['cost_center_ids'];
        /** @var Currency|null $baseCurrency */
        $baseCurrency = $scope['base_currency'];
        /** @var Collection<int, Currency> $currencies */
        $currencies = $scope['currencies'];

        // 1. تفاصيل الخزائن والبنوك والأرصدة النقدية
        $treasuryData = $this->buildTreasuryDetails(
            $endDate,
            $prevEndDate,
            $validated['branch_id'] ?? null,
            $baseCurrency,
            $currencies
        );

        // 2. مؤشرات الأداء الرئيسية (KPIs) ونسب النمو والـ Sparklines
        $kpis = $this->buildKpis(
            $startDate,
            $endDate,
            $prevStartDate,
            $prevEndDate,
            $costCenterIds,
            $treasuryData['total_liquidity_base'],
            $treasuryData['prev_total_liquidity_base']
        );

        // 3. بيانات الرسوم البيانية الستة (6 Charts)
        $charts = [
            'monthly_cashflow'      => $this->buildMonthlyCashflowChart($startDate, $endDate, $costCenterIds),
            'treasury_distribution' => $treasuryData['chart'],
            'top_expenses'          => $this->buildTopExpensesChart($startDate, $endDate, $costCenterIds, $kpis['expenses']['value']),
            'entries_by_source'     => $this->buildEntriesBySourceChart($startDate, $endDate, $costCenterIds),
            'budget_execution'      => $this->buildBudgetExecutionChart($scope['fiscal_year'], $startDate, $endDate, $costCenterIds),
            'branches_comparison'   => $this->buildBranchesComparisonChart($startDate, $endDate),
        ];

        // 4. أعلى العملاء المدينين والموردين الدائنين
        $topParties = $this->buildTopParties($endDate, $costCenterIds);

        // 5. التنبيهات الإجرائية وآخر الحركات المرحّلة
        $alertsAndActivities = $this->buildAlertsAndActivities($validated['branch_id'] ?? null, $costCenterIds);

        return response()->json([
            'status' => true,
            'data'   => [
                'meta' => [
                    'fiscal_year'   => $scope['fiscal_year'] ? [
                        'id'         => $scope['fiscal_year']->id,
                        'name'       => $scope['fiscal_year']->name,
                        'start_date' => $scope['fiscal_year']->start_date?->format('Y-m-d'),
                        'end_date'   => $scope['fiscal_year']->end_date?->format('Y-m-d'),
                        'status'     => $scope['fiscal_year']->status?->value,
                    ] : null,
                    'date_from'     => $startDate->format('Y-m-d'),
                    'date_to'       => $endDate->format('Y-m-d'),
                    'branch_id'     => isset($validated['branch_id']) ? (int) $validated['branch_id'] : null,
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
                'kpis'                  => $kpis,
                'charts'                => $charts,
                'treasury_accounts'     => $treasuryData['accounts'],
                'liquidity_by_currency' => $treasuryData['by_currency'],
                'top_parties'           => $topParties,
                'alerts'                => $alertsAndActivities['alerts'],
                'recent_activities'     => $alertsAndActivities['recent_activities'],
            ],
        ]);
    }

    /**
     * تحديد السنة المالية، النطاق الزمني، الفترة المقارنة، وشجرة مراكز التكلفة للفرع
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function resolvePeriodAndScope(array $filters): array
    {
        $today = Carbon::today();

        if (! empty($filters['fiscal_year_id'])) {
            $fiscalYear = FiscalYear::find((int) $filters['fiscal_year_id']);
        } else {
            $fiscalYear = FiscalYear::where('status', FiscalYearStatus::Open)
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->first()
                ?? FiscalYear::where('status', FiscalYearStatus::Open)->orderByDesc('start_date')->first()
                ?? FiscalYear::orderByDesc('start_date')->first();
        }

        if (! empty($filters['date_from'])) {
            $startDate = Carbon::parse((string) $filters['date_from'])->startOfDay();
        } elseif ($fiscalYear?->start_date) {
            $startDate = $fiscalYear->start_date->copy()->startOfDay();
        } else {
            $startDate = $today->copy()->startOfYear();
        }

        if (! empty($filters['date_to'])) {
            $endDate = Carbon::parse((string) $filters['date_to'])->endOfDay();
        } elseif ($fiscalYear?->end_date) {
            $endDate = $fiscalYear->end_date->copy()->endOfDay();
        } else {
            $endDate = $today->copy()->endOfYear();
        }

        if ($endDate->lt($startDate)) {
            $endDate = $startDate->copy()->endOfDay();
        }

        // حساب الفترة السابقة المساوية تماماً في عدد الأيام للمقارنة العادلة
        $daysCount = max(1, (int) $startDate->diffInDays($endDate) + 1);
        $prevEndDate = $startDate->copy()->subDay()->endOfDay();
        $prevStartDate = $prevEndDate->copy()->subDays($daysCount - 1)->startOfDay();

        // استخراج الفرع وكافة مراكز التكلفة الفرعية التابعة له
        $costCenterIds = null;
        if (! empty($filters['branch_id'])) {
            $branch = CostCenter::find((int) $filters['branch_id']);
            if ($branch) {
                $costCenterIds = method_exists($branch, 'descendants')
                    ? $branch->descendants()->pluck('id')->prepend($branch->id)->unique()->values()->toArray()
                    : [$branch->id];
            }
        }

        $currencies = Currency::all()->keyBy('id');
        $baseCurrency = $currencies->firstWhere('is_base', true) ?? $currencies->first();

        return [
            'fiscal_year'     => $fiscalYear,
            'start_date'      => $startDate,
            'end_date'        => $endDate,
            'prev_start_date' => $prevStartDate,
            'prev_end_date'   => $prevEndDate,
            'cost_center_ids' => $costCenterIds,
            'currencies'      => $currencies,
            'base_currency'   => $baseCurrency,
        ];
    }

    /**
     * بناء بطاقات المؤشرات الرئيسية (KPIs) مع نسب التغير والرسوم المصغرة
     *
     * @param array<int, int>|null $costCenterIds
     * @return array<string, mixed>
     */
    private function buildKpis(
        Carbon $startDate,
        Carbon $endDate,
        Carbon $prevStartDate,
        Carbon $prevEndDate,
        ?array $costCenterIds,
        float $totalLiquidity,
        float $prevTotalLiquidity
    ): array {
        // أرصدة قائمة الدخل للفترة الحالية والسابقة
        $currentFlow = $this->aggregateAccountTypesByRange($startDate, $endDate, $costCenterIds);
        $prevFlow = $this->aggregateAccountTypesByRange($prevStartDate, $prevEndDate, $costCenterIds);

        $revenue = $currentFlow[AccountType::REVENUE->value] ?? 0.0;
        $prevRevenue = $prevFlow[AccountType::REVENUE->value] ?? 0.0;

        $expenses = $currentFlow[AccountType::EXPENSE->value] ?? 0.0;
        $prevExpenses = $prevFlow[AccountType::EXPENSE->value] ?? 0.0;

        $netProfit = round($revenue - $expenses, 2);
        $prevNetProfit = round($prevRevenue - $prevExpenses, 2);

        $profitMargin = $revenue > 0 ? round(($netProfit / $revenue) * 100, 2) : 0.0;
        $prevProfitMargin = $prevRevenue > 0 ? round(($prevNetProfit / $prevRevenue) * 100, 2) : 0.0;

        // أرصدة الميزانية التراكمية حتى تاريخ النهاية
        $currentCumulative = $this->aggregateAccountTypesUpToDate($endDate, $costCenterIds);
        $prevCumulative = $this->aggregateAccountTypesUpToDate($prevEndDate, $costCenterIds);

        $totalAssets = $currentCumulative[AccountType::ASSET->value] ?? 0.0;
        $prevTotalAssets = $prevCumulative[AccountType::ASSET->value] ?? 0.0;

        $totalLiabilities = $currentCumulative[AccountType::LIABILITY->value] ?? 0.0;
        $prevTotalLiabilities = $prevCumulative[AccountType::LIABILITY->value] ?? 0.0;

        // نقاط Sparkline لآخر 6 أشهر
        $sparklines = $this->buildKpiSparklines($endDate, $costCenterIds);

        return [
            'liquidity' => [
                'value'             => round($totalLiquidity, 2),
                'previous_value'    => round($prevTotalLiquidity, 2),
                'growth_percentage' => $this->calculateGrowth($totalLiquidity, $prevTotalLiquidity),
                'sparkline'         => $sparklines['liquidity'],
            ],
            'revenue' => [
                'value'             => round($revenue, 2),
                'previous_value'    => round($prevRevenue, 2),
                'growth_percentage' => $this->calculateGrowth($revenue, $prevRevenue),
                'sparkline'         => $sparklines['revenue'],
            ],
            'expenses' => [
                'value'             => round($expenses, 2),
                'previous_value'    => round($prevExpenses, 2),
                'growth_percentage' => $this->calculateGrowth($expenses, $prevExpenses),
                'sparkline'         => $sparklines['expenses'],
            ],
            'net_profit' => [
                'value'             => $netProfit,
                'previous_value'    => $prevNetProfit,
                'growth_percentage' => $this->calculateGrowth($netProfit, $prevNetProfit),
                'sparkline'         => $sparklines['net_profit'],
            ],
            'profit_margin' => [
                'value'             => $profitMargin,
                'previous_value'    => $prevProfitMargin,
                'growth_percentage' => round($profitMargin - $prevProfitMargin, 2),
            ],
            'total_assets' => [
                'value'             => round($totalAssets, 2),
                'previous_value'    => round($prevTotalAssets, 2),
                'growth_percentage' => $this->calculateGrowth($totalAssets, $prevTotalAssets),
            ],
            'total_liabilities' => [
                'value'             => round($totalLiabilities, 2),
                'previous_value'    => round($prevTotalLiabilities, 2),
                'growth_percentage' => $this->calculateGrowth($totalLiabilities, $prevTotalLiabilities),
            ],
        ];
    }

    /**
     * احتساب تفاصيل أرصدة الخزائن والحسابات البنكية (بالعملة الأصلية والمعادل للعملة الأساسية)
     *
     * @param Collection<int, Currency> $currencies
     * @return array<string, mixed>
     */
    private function buildTreasuryDetails(
        Carbon $endDate,
        Carbon $prevEndDate,
        mixed $branchId,
        ?Currency $baseCurrency,
        Collection $currencies
    ): array {
        $boxesQuery = Box::query()->where('is_active', true);
        $banksQuery = BankAccount::query()->where('is_active', true);

        if (! empty($branchId)) {
            $boxesQuery->where('branch_id', (int) $branchId);
            $banksQuery->where('branch_id', (int) $branchId);
        }

        $boxes = $boxesQuery->get();
        $banks = $banksQuery->get();

        $allAccountIds = $boxes->pluck('account_id')
            ->merge($banks->pluck('account_id'))
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $currentBalances = $this->getAccountsBalanceUpToDate($allAccountIds, $endDate);
        $prevBalances = $this->getAccountsBalanceUpToDate($allAccountIds, $prevEndDate);

        $accountsList = [];
        $currencyGroups = [];

        $totalBoxesBase = 0.0;
        $totalBanksBase = 0.0;
        $prevTotalLiquidityBase = 0.0;

        foreach ($boxes as $box) {
            $currency = $currencies->get($box->currency_id) ?? $baseCurrency;
            $rate = ($currency && ! $currency->is_base && (float) $currency->exchange_rate > 0)
                ? (float) $currency->exchange_rate
                : 1.0;

            $nativeBalance = round((float) ($currentBalances[$box->account_id] ?? 0.0), 2);
            $baseBalance = round($nativeBalance * $rate, 2);

            $prevNative = (float) ($prevBalances[$box->account_id] ?? 0.0);
            $prevTotalLiquidityBase += ($prevNative * $rate);

            $totalBoxesBase += $baseBalance;

            $accountsList[] = [
                'id'              => $box->id,
                'kind'            => 'box',
                'kind_label'      => 'خزينة نقدية',
                'name'            => $box->name,
                'account_number'  => null,
                'account_id'      => $box->account_id,
                'currency_code'   => $currency?->code ?? 'SDG',
                'currency_symbol' => $currency?->symbol ?? '',
                'exchange_rate'   => $rate,
                'native_balance'  => $nativeBalance,
                'base_balance'    => $baseBalance,
            ];

            $this->accumulateCurrencyGroup($currencyGroups, $currency, $nativeBalance, $baseBalance);
        }

        foreach ($banks as $bank) {
            $currency = $currencies->get($bank->currency_id) ?? $baseCurrency;
            $rate = ($currency && ! $currency->is_base && (float) $currency->exchange_rate > 0)
                ? (float) $currency->exchange_rate
                : 1.0;

            $nativeBalance = round((float) ($currentBalances[$bank->account_id] ?? 0.0), 2);
            $baseBalance = round($nativeBalance * $rate, 2);

            $prevNative = (float) ($prevBalances[$bank->account_id] ?? 0.0);
            $prevTotalLiquidityBase += ($prevNative * $rate);

            $totalBanksBase += $baseBalance;

            $accountsList[] = [
                'id'              => $bank->id,
                'kind'            => 'bank',
                'kind_label'      => 'حساب بنكي',
                'name'            => $bank->bank_name . ' - ' . $bank->account_name,
                'account_number'  => $bank->account_number,
                'account_id'      => $bank->account_id,
                'currency_code'   => $currency?->code ?? 'SDG',
                'currency_symbol' => $currency?->symbol ?? '',
                'exchange_rate'   => $rate,
                'native_balance'  => $nativeBalance,
                'base_balance'    => $baseBalance,
            ];

            $this->accumulateCurrencyGroup($currencyGroups, $currency, $nativeBalance, $baseBalance);
        }

        // ترتيب الحسابات تنازلياً حسب الرصيد بالعملة الأساسية
        usort($accountsList, fn (array $a, array $b) => $b['base_balance'] <=> $a['base_balance']);

        $totalLiquidityBase = round($totalBoxesBase + $totalBanksBase, 2);

        return [
            'total_liquidity_base'      => $totalLiquidityBase,
            'prev_total_liquidity_base' => round($prevTotalLiquidityBase, 2),
            'accounts'                  => $accountsList,
            'by_currency'               => array_values($currencyGroups),
            'chart'                     => [
                'labels' => ['الخزائن النقدية', 'الحسابات البنكية'],
                'series' => [round($totalBoxesBase, 2), round($totalBanksBase, 2)],
                'items'  => array_map(fn (array $item) => [
                    'name'  => $item['name'],
                    'kind'  => $item['kind'],
                    'value' => $item['base_balance'],
                ], array_slice($accountsList, 0, 8)),
            ],
        ];
    }

    /**
     * Chart 1: التدفق المالي الشهري (الإيرادات، المصروفات، صافي الربح)
     *
     * @param array<int, int>|null $costCenterIds
     * @return array<string, mixed>
     */
    private function buildMonthlyCashflowChart(Carbon $startDate, Carbon $endDate, ?array $costCenterIds): array
    {
        $arabicMonths = [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
            5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
        ];

        // تهيئة الأشهر ضمن النطاق (بحد أقصى 12 شهراً للوضوح البصري)
        $chartStart = $startDate->copy()->startOfMonth();
        $chartEnd = $endDate->copy()->endOfMonth();

        if ($chartStart->diffInMonths($chartEnd) > 11) {
            $chartStart = $chartEnd->copy()->subMonths(11)->startOfMonth();
        }

        $monthlyMap = [];
        $period = CarbonPeriod::create($chartStart, '1 month', $chartEnd);

        foreach ($period as $monthDate) {
            $key = $monthDate->format('Y-m');
            $monthlyMap[$key] = [
                'label'    => $arabicMonths[(int) $monthDate->month] . ' ' . $monthDate->year,
                'revenue'  => 0.0,
                'expenses' => 0.0,
                'profit'   => 0.0,
            ];
        }

        $query = JournalEntryDetail::query()
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_entry_details.account_id', '=', 'accounts.id')
            ->whereNull('journal_entries.deleted_at')
            ->whereNull('accounts.deleted_at')
            ->where('journal_entries.status', EntryStatus::Posted->value)
            ->whereIn('accounts.type', [AccountType::REVENUE->value, AccountType::EXPENSE->value])
            ->whereBetween('journal_entries.date', [
                $chartStart->format('Y-m-d'),
                $chartEnd->format('Y-m-d'),
            ]);

        if ($costCenterIds !== null) {
            $query->whereIn('journal_entry_details.cost_center_id', $costCenterIds);
        }

        $rows = $query->select([
            'journal_entries.date as entry_date',
            'accounts.type as account_type',
            'journal_entry_details.debit',
            'journal_entry_details.credit',
        ])->get();

        foreach ($rows as $row) {
            $monthKey = Carbon::parse((string) $row->entry_date)->format('Y-m');
            if (! isset($monthlyMap[$monthKey])) {
                continue;
            }

            $debit = (float) $row->debit;
            $credit = (float) $row->credit;

            if ($row->account_type === AccountType::REVENUE->value) {
                $monthlyMap[$monthKey]['revenue'] += ($credit - $debit);
            } elseif ($row->account_type === AccountType::EXPENSE->value) {
                $monthlyMap[$monthKey]['expenses'] += ($debit - $credit);
            }
        }

        $labels = [];
        $revenues = [];
        $expenses = [];
        $profits = [];

        foreach ($monthlyMap as $item) {
            $rev = round($item['revenue'], 2);
            $exp = round($item['expenses'], 2);
            $prof = round($rev - $exp, 2);

            $labels[] = $item['label'];
            $revenues[] = $rev;
            $expenses[] = $exp;
            $profits[] = $prof;
        }

        return [
            'labels'   => $labels,
            'revenues' => $revenues,
            'expenses' => $expenses,
            'profits'  => $profits,
        ];
    }

    /**
     * Chart 3: أعلى 6 حسابات مصروفات خلال الفترة
     *
     * @param array<int, int>|null $costCenterIds
     * @return array<string, mixed>
     */
    private function buildTopExpensesChart(
        Carbon $startDate,
        Carbon $endDate,
        ?array $costCenterIds,
        float $totalExpenses
    ): array {
        $query = JournalEntryDetail::query()
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_entry_details.account_id', '=', 'accounts.id')
            ->whereNull('journal_entries.deleted_at')
            ->whereNull('accounts.deleted_at')
            ->where('journal_entries.status', EntryStatus::Posted->value)
            ->where('accounts.type', AccountType::EXPENSE->value)
            ->whereBetween('journal_entries.date', [
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d'),
            ]);

        if ($costCenterIds !== null) {
            $query->whereIn('journal_entry_details.cost_center_id', $costCenterIds);
        }

        $rows = $query->selectRaw('
                accounts.id as account_id,
                accounts.code as account_code,
                accounts.name as account_name,
                COALESCE(SUM(journal_entry_details.debit - journal_entry_details.credit), 0) as total_amount
            ')
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name')
            ->havingRaw('COALESCE(SUM(journal_entry_details.debit - journal_entry_details.credit), 0) > 0')
            ->orderByDesc('total_amount')
            ->limit(6)
            ->get();

        $items = $rows->map(function ($row) use ($totalExpenses) {
            $amount = round((float) $row->total_amount, 2);
            $percentage = $totalExpenses > 0 ? round(($amount / $totalExpenses) * 100, 1) : 0.0;

            return [
                'account_id'   => (int) $row->account_id,
                'account_code' => $row->account_code,
                'account_name' => $row->account_name,
                'amount'       => $amount,
                'percentage'   => $percentage,
            ];
        })->values();

        return [
            'labels' => $items->pluck('account_name')->toArray(),
            'series' => $items->pluck('amount')->toArray(),
            'items'  => $items->toArray(),
        ];
    }

    /**
     * Chart 4: تحليل مصادر القيود المرحّلة (مبيعات، مشتريات، رواتب، سندات، يدوي...)
     *
     * @param array<int, int>|null $costCenterIds
     * @return array<string, mixed>
     */
    private function buildEntriesBySourceChart(Carbon $startDate, Carbon $endDate, ?array $costCenterIds): array
    {
        $query = JournalEntryDetail::query()
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->whereNull('journal_entries.deleted_at')
            ->where('journal_entries.status', EntryStatus::Posted->value)
            ->whereBetween('journal_entries.date', [
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d'),
            ]);

        if ($costCenterIds !== null) {
            $query->whereIn('journal_entry_details.cost_center_id', $costCenterIds);
        }

        $rows = $query->selectRaw('
                journal_entries.source as source,
                COUNT(DISTINCT journal_entries.id) as entries_count,
                COALESCE(SUM(journal_entry_details.debit), 0) as total_volume
            ')
            ->groupBy('journal_entries.source')
            ->orderByDesc('total_volume')
            ->get();

        $items = $rows->map(function ($row) {
            $sourceValue = (string) ($row->source instanceof EntrySource ? $row->source->value : $row->source);
            $enumCase = EntrySource::tryFrom($sourceValue);

            return [
                'source'        => $sourceValue,
                'label'         => $enumCase ? $enumCase->label() : $sourceValue,
                'entries_count' => (int) $row->entries_count,
                'total_volume'  => round((float) $row->total_volume, 2),
            ];
        })->values();

        return [
            'labels' => $items->pluck('label')->toArray(),
            'series' => $items->pluck('total_volume')->toArray(),
            'counts' => $items->pluck('entries_count')->toArray(),
            'items'  => $items->toArray(),
        ];
    }

    /**
     * Chart 5: مؤشر تنفيذ الموازنة التقديرية النشطة (Planned vs Actual)
     *
     * @param array<int, int>|null $costCenterIds
     * @return array<string, mixed>
     */
    private function buildBudgetExecutionChart(
        ?FiscalYear $fiscalYear,
        Carbon $startDate,
        Carbon $endDate,
        ?array $costCenterIds
    ): array {
        $budgetQuery = Budget::query()
            ->whereIn('status', [BudgetStatus::Active->value, BudgetStatus::Approved->value])
            ->with(['lines.account', 'lines.costCenter']);

        if ($fiscalYear) {
            $budgetQuery->where(function ($q) use ($fiscalYear, $startDate, $endDate) {
                $q->where('fiscal_year_id', $fiscalYear->id)
                  ->orWhere(function ($sub) use ($startDate, $endDate) {
                      $sub->whereDate('start_date', '<=', $endDate->format('Y-m-d'))
                          ->whereDate('end_date', '>=', $startDate->format('Y-m-d'));
                  });
            });
        }

        $budget = $budgetQuery->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->first();

        if (! $budget) {
            return [
                'has_active_budget'    => false,
                'budget_name'          => null,
                'planned_total'        => 0.0,
                'actual_total'         => 0.0,
                'remaining_total'      => 0.0,
                'execution_percentage' => 0.0,
                'items'                => [],
            ];
        }

        $lines = $budget->lines;

        if ($costCenterIds !== null) {
            $lines = $lines->filter(fn ($line) => is_null($line->cost_center_id) || in_array($line->cost_center_id, $costCenterIds, true));
        }

        $plannedTotal = 0.0;
        $actualTotal = 0.0;
        $groupedItems = [];

        foreach ($lines as $line) {
            $planned = (float) $line->planned_amount;
            $plannedTotal += $planned;

            $consumedQuery = JournalEntryDetail::query()
                ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
                ->whereNull('journal_entries.deleted_at')
                ->where('journal_entries.status', EntryStatus::Posted->value)
                ->whereBetween('journal_entries.date', [
                    $line->period_start->format('Y-m-d'),
                    $line->period_end->format('Y-m-d'),
                ]);

            if ($line->account_id) {
                $consumedQuery->where('journal_entry_details.account_id', $line->account_id);
            } else {
                $consumedQuery->join('accounts', 'journal_entry_details.account_id', '=', 'accounts.id')
                    ->where('accounts.type', AccountType::EXPENSE->value);
            }

            if ($line->cost_center_id) {
                $cc = $line->costCenter;
                $ccIds = ($cc && method_exists($cc, 'descendants'))
                    ? $cc->descendants()->pluck('id')->prepend($cc->id)->toArray()
                    : [$line->cost_center_id];

                $consumedQuery->whereIn('journal_entry_details.cost_center_id', $ccIds);
            } elseif ($costCenterIds !== null) {
                $consumedQuery->whereIn('journal_entry_details.cost_center_id', $costCenterIds);
            }

            $sums = $consumedQuery->selectRaw('COALESCE(SUM(journal_entry_details.debit - journal_entry_details.credit), 0) as net_spent')->first();
            $actual = max(0.0, (float) ($sums->net_spent ?? 0.0));
            $actualTotal += $actual;

            $label = $line->account?->name
                ?? $line->costCenter?->name
                ?? ('بند #' . $line->id);

            $groupKey = ($line->account_id ?? '0') . '_' . ($line->cost_center_id ?? '0');

            if (! isset($groupedItems[$groupKey])) {
                $groupedItems[$groupKey] = [
                    'label'   => $label,
                    'planned' => 0.0,
                    'actual'  => 0.0,
                ];
            }

            $groupedItems[$groupKey]['planned'] += $planned;
            $groupedItems[$groupKey]['actual'] += $actual;
        }

        $itemsList = array_values(array_map(function (array $item) {
            $planned = round($item['planned'], 2);
            $actual = round($item['actual'], 2);
            $pct = $planned > 0 ? round(($actual / $planned) * 100, 1) : 0.0;

            return [
                'label'      => $item['label'],
                'planned'    => $planned,
                'actual'     => $actual,
                'percentage' => $pct,
            ];
        }, $groupedItems));

        usort($itemsList, fn (array $a, array $b) => $b['planned'] <=> $a['planned']);
        $topItems = array_slice($itemsList, 0, 6);

        $plannedTotal = round($plannedTotal, 2);
        $actualTotal = round($actualTotal, 2);
        $executionPercentage = $plannedTotal > 0 ? round(($actualTotal / $plannedTotal) * 100, 1) : 0.0;

        return [
            'has_active_budget'    => true,
            'budget_id'            => $budget->id,
            'budget_name'          => $budget->name,
            'control_mode'         => $budget->control_mode?->value,
            'planned_total'        => $plannedTotal,
            'actual_total'         => $actualTotal,
            'remaining_total'      => round($plannedTotal - $actualTotal, 2),
            'execution_percentage' => $executionPercentage,
            'labels'               => array_column($topItems, 'label'),
            'planned_series'       => array_column($topItems, 'planned'),
            'actual_series'        => array_column($topItems, 'actual'),
            'items'                => $topItems,
        ];
    }

    /**
     * Chart 6: مقارنة أداء الفروع (الإيرادات مقابل المصروفات لكل فرع)
     *
     * @return array<string, mixed>
     */
    private function buildBranchesComparisonChart(Carbon $startDate, Carbon $endDate): array
    {
        $branches = CostCenter::where('is_branch', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        if ($branches->isEmpty()) {
            return [
                'labels'   => [],
                'revenues' => [],
                'expenses' => [],
                'profits'  => [],
            ];
        }

        $labels = [];
        $revenues = [];
        $expenses = [];
        $profits = [];

        foreach ($branches as $branch) {
            $branchCcIds = method_exists($branch, 'descendants')
                ? $branch->descendants()->pluck('id')->prepend($branch->id)->unique()->values()->toArray()
                : [$branch->id];

            $flow = $this->aggregateAccountTypesByRange($startDate, $endDate, $branchCcIds);
            $rev = round($flow[AccountType::REVENUE->value] ?? 0.0, 2);
            $exp = round($flow[AccountType::EXPENSE->value] ?? 0.0, 2);

            $labels[] = $branch->name;
            $revenues[] = $rev;
            $expenses[] = $exp;
            $profits[] = round($rev - $exp, 2);
        }

        return [
            'labels'   => $labels,
            'revenues' => $revenues,
            'expenses' => $expenses,
            'profits'  => $profits,
        ];
    }

    /**
     * استخراج أعلى 5 أطراف مدينة (عملاء) وأعلى 5 أطراف دائنة (موردون)
     *
     * @param array<int, int>|null $costCenterIds
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildTopParties(Carbon $endDate, ?array $costCenterIds): array
    {
        $baseQuery = JournalEntryDetail::query()
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_entry_details.account_id', '=', 'accounts.id')
            ->whereNull('journal_entries.deleted_at')
            ->whereNull('accounts.deleted_at')
            ->where('journal_entries.status', EntryStatus::Posted->value)
            ->whereNotNull('journal_entry_details.party_type')
            ->whereNotNull('journal_entry_details.party_id')
            ->whereDate('journal_entries.date', '<=', $endDate->format('Y-m-d'));

        if ($costCenterIds !== null) {
            $baseQuery->whereIn('journal_entry_details.cost_center_id', $costCenterIds);
        }

        // أعلى 5 مدينين (حسابات أصول رصيدها المدين > 0)
        $debtorsRaw = (clone $baseQuery)
            ->where('accounts.type', AccountType::ASSET->value)
            ->selectRaw('
                journal_entry_details.party_type,
                journal_entry_details.party_id,
                COALESCE(SUM(journal_entry_details.debit - journal_entry_details.credit), 0) as balance
            ')
            ->groupBy('journal_entry_details.party_type', 'journal_entry_details.party_id')
            ->havingRaw('COALESCE(SUM(journal_entry_details.debit - journal_entry_details.credit), 0) > 0')
            ->orderByDesc('balance')
            ->limit(5)
            ->get();

        // أعلى 5 دائنين (حسابات التزامات رصيدها الدائن > 0)
        $creditorsRaw = (clone $baseQuery)
            ->where('accounts.type', AccountType::LIABILITY->value)
            ->selectRaw('
                journal_entry_details.party_type,
                journal_entry_details.party_id,
                COALESCE(SUM(journal_entry_details.credit - journal_entry_details.debit), 0) as balance
            ')
            ->groupBy('journal_entry_details.party_type', 'journal_entry_details.party_id')
            ->havingRaw('COALESCE(SUM(journal_entry_details.credit - journal_entry_details.debit), 0) > 0')
            ->orderByDesc('balance')
            ->limit(5)
            ->get();

        return [
            'top_debtors'   => $this->hydratePartyNames($debtorsRaw),
            'top_creditors' => $this->hydratePartyNames($creditorsRaw),
        ];
    }

    /**
     * التنبيهات الإجرائية (المسودات والمعلقات) وآخر 8 حركات مالية مرحّلة
     *
     * @param array<int, int>|null $costCenterIds
     * @return array<string, mixed>
     */
    private function buildAlertsAndActivities(mixed $branchId, ?array $costCenterIds): array
    {
        $draftEntriesQuery = JournalEntry::where('status', EntryStatus::Draft->value);

        if ($costCenterIds !== null) {
            $draftEntriesQuery->whereHas('details', fn ($q) => $q->whereIn('cost_center_id', $costCenterIds));
        }

        $pendingVouchersQuery = Voucher::where('status', VoucherStatus::PendingApproval->value);
        $unpostedVouchersQuery = Voucher::whereIn('status', [
            VoucherStatus::Draft->value,
            VoucherStatus::Approved->value,
        ]);

        if (! empty($branchId)) {
            $pendingVouchersQuery->where('branch_id', (int) $branchId);
            $unpostedVouchersQuery->where('branch_id', (int) $branchId);
        }

        $recentQuery = JournalEntry::query()
            ->where('status', EntryStatus::Posted->value)
            ->with(['creator:id,name'])
            ->withSum('details as total_amount', 'debit');

        if ($costCenterIds !== null) {
            $recentQuery->whereHas('details', fn ($q) => $q->whereIn('cost_center_id', $costCenterIds));
        }

        $recentEntries = $recentQuery
            ->orderByDesc('posted_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(function (JournalEntry $entry) {
                $sourceEnum = $entry->source instanceof EntrySource
                    ? $entry->source
                    : EntrySource::tryFrom((string) $entry->source);

                return [
                    'id'           => $entry->id,
                    'entry_number' => $entry->entry_number,
                    'date'         => $entry->date?->format('Y-m-d'),
                    'source'       => $sourceEnum?->value ?? (string) $entry->source,
                    'source_label' => $sourceEnum?->label() ?? (string) $entry->source,
                    'description'  => $entry->description,
                    'total_amount' => round((float) ($entry->total_amount ?? 0.0), 2),
                    'created_by'   => $entry->creator?->name,
                    'posted_at'    => $entry->posted_at?->format('Y-m-d H:i'),
                ];
            })
            ->values()
            ->toArray();

        return [
            'alerts' => [
                'draft_entries_count'     => $draftEntriesQuery->count(),
                'pending_vouchers_count'  => $pendingVouchersQuery->count(),
                'unposted_vouchers_count' => $unpostedVouchersQuery->count(),
            ],
            'recent_activities' => $recentEntries,
        ];
    }

    /**
     * تجميع أرصدة أنواع الحسابات خلال نطاق زمني (للإيرادات والمصروفات)
     *
     * @param array<int, int>|null $costCenterIds
     * @return array<string, float>
     */
    private function aggregateAccountTypesByRange(Carbon $startDate, Carbon $endDate, ?array $costCenterIds): array
    {
        $query = JournalEntryDetail::query()
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_entry_details.account_id', '=', 'accounts.id')
            ->whereNull('journal_entries.deleted_at')
            ->whereNull('accounts.deleted_at')
            ->where('journal_entries.status', EntryStatus::Posted->value)
            ->whereBetween('journal_entries.date', [
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d'),
            ]);

        if ($costCenterIds !== null) {
            $query->whereIn('journal_entry_details.cost_center_id', $costCenterIds);
        }

        $rows = $query->selectRaw('
                accounts.type as account_type,
                COALESCE(SUM(journal_entry_details.debit), 0) as total_debit,
                COALESCE(SUM(journal_entry_details.credit), 0) as total_credit
            ')
            ->groupBy('accounts.type')
            ->get();

        $totals = [];
        foreach ($rows as $row) {
            $type = (string) $row->account_type;
            $debit = (float) $row->total_debit;
            $credit = (float) $row->total_credit;

            $totals[$type] = in_array($type, [AccountType::ASSET->value, AccountType::EXPENSE->value], true)
                ? ($debit - $credit)
                : ($credit - $debit);
        }

        return $totals;
    }

    /**
     * تجميع الأرصدة التراكمية لأنواع الحسابات حتى تاريخ محدد (للأصول والالتزامات وحقوق الملكية)
     *
     * @param array<int, int>|null $costCenterIds
     * @return array<string, float>
     */
    private function aggregateAccountTypesUpToDate(Carbon $upToDate, ?array $costCenterIds): array
    {
        $query = JournalEntryDetail::query()
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_entry_details.account_id', '=', 'accounts.id')
            ->whereNull('journal_entries.deleted_at')
            ->whereNull('accounts.deleted_at')
            ->where('journal_entries.status', EntryStatus::Posted->value)
            ->whereDate('journal_entries.date', '<=', $upToDate->format('Y-m-d'));

        if ($costCenterIds !== null) {
            $query->whereIn('journal_entry_details.cost_center_id', $costCenterIds);
        }

        $rows = $query->selectRaw('
                accounts.type as account_type,
                COALESCE(SUM(journal_entry_details.debit), 0) as total_debit,
                COALESCE(SUM(journal_entry_details.credit), 0) as total_credit
            ')
            ->groupBy('accounts.type')
            ->get();

        $totals = [];
        foreach ($rows as $row) {
            $type = (string) $row->account_type;
            $debit = (float) $row->total_debit;
            $credit = (float) $row->total_credit;

            $totals[$type] = in_array($type, [AccountType::ASSET->value, AccountType::EXPENSE->value], true)
                ? ($debit - $credit)
                : ($credit - $debit);
        }

        return $totals;
    }

    /**
     * جلب أرصدة مجموعة حسابات (طبيعة مدينة) حتى تاريخ محدد دفعة واحدة
     *
     * @param array<int, int> $accountIds
     * @return array<int, float>
     */
    private function getAccountsBalanceUpToDate(array $accountIds, Carbon $upToDate): array
    {
        if (empty($accountIds)) {
            return [];
        }

        return JournalEntryDetail::query()
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->whereNull('journal_entries.deleted_at')
            ->where('journal_entries.status', EntryStatus::Posted->value)
            ->whereIn('journal_entry_details.account_id', $accountIds)
            ->whereDate('journal_entries.date', '<=', $upToDate->format('Y-m-d'))
            ->selectRaw('
                journal_entry_details.account_id,
                COALESCE(SUM(journal_entry_details.debit - journal_entry_details.credit), 0) as balance
            ')
            ->groupBy('journal_entry_details.account_id')
            ->pluck('balance', 'account_id')
            ->map(fn ($val) => (float) $val)
            ->toArray();
    }

    /**
     * بناء سلسلة مصغرة (Sparkline) لآخر 6 أشهر لبطاقات الـ KPI
     *
     * @param array<int, int>|null $costCenterIds
     * @return array<string, array<int, float>>
     */
    private function buildKpiSparklines(Carbon $endDate, ?array $costCenterIds): array
    {
        $start6M = $endDate->copy()->subMonths(5)->startOfMonth();
        $monthly = $this->buildMonthlyCashflowChart($start6M, $endDate, $costCenterIds);

        // بناء تدفق السيولة التراكمي لآخر 6 أشهر
        $liquidityPoints = [];
        $runningProfit = 0.0;
        foreach ($monthly['profits'] as $p) {
            $runningProfit += $p;
            $liquidityPoints[] = round($runningProfit, 2);
        }

        return [
            'revenue'    => $monthly['revenues'],
            'expenses'   => $monthly['expenses'],
            'net_profit' => $monthly['profits'],
            'liquidity'  => $liquidityPoints,
        ];
    }

    /**
     * تجميع السيولة حسب كل عملة على حدة
     *
     * @param array<string, array<string, mixed>> $groups
     */
    private function accumulateCurrencyGroup(array &$groups, ?Currency $currency, float $nativeAmount, float $baseAmount): void
    {
        $code = $currency?->code ?? 'SDG';

        if (! isset($groups[$code])) {
            $groups[$code] = [
                'currency_code'   => $code,
                'currency_name'   => $currency?->name ?? 'العملة الأساسية',
                'currency_symbol' => $currency?->symbol ?? '',
                'native_total'    => 0.0,
                'base_total'      => 0.0,
            ];
        }

        $groups[$code]['native_total'] = round($groups[$code]['native_total'] + $nativeAmount, 2);
        $groups[$code]['base_total'] = round($groups[$code]['base_total'] + $baseAmount, 2);
    }

    /**
     * تحميل أسماء الأطراف البوليمورفية بأمان (عملاء / موردون / موظفون)
     *
     * @param Collection<int, object> $rows
     * @return array<int, array<string, mixed>>
     */
    private function hydratePartyNames(Collection $rows): array
    {
        return $rows->map(function ($row) {
            $partyType = (string) $row->party_type;
            $partyId = $row->party_id;
            $name = 'طرف #' . $partyId;

            try {
                if (class_exists($partyType)) {
                    $record = $partyType::find($partyId);
                    if ($record) {
                        $name = $record->name
                            ?? $record->full_name
                            ?? $record->company_name
                            ?? $name;
                    }
                }
            } catch (Throwable) {
                // تجاهل أي تعارض في اسم الموديل والحفاظ على الاسم البديل الآمن
            }

            return [
                'party_type' => class_basename($partyType),
                'party_id'   => $partyId,
                'name'       => $name,
                'balance'    => round((float) $row->balance, 2),
            ];
        })->values()->toArray();
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