<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Services\Reporting;

use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Accounting\Enums\EntryStatus;
use App\Modules\Accounting\Models\Budget;
use App\Modules\Accounting\Models\BudgetLine;
use App\Modules\Accounting\Models\CostCenter;
use App\Modules\Accounting\Models\JournalEntryDetail;
use Illuminate\Support\Collection;

class BudgetVarianceService
{
    /**
     * استخراج تقرير الانحراف التفصيلي لموازنة معينة
     */
    public function getBudgetVarianceReport(Budget $budget, array $filters = []): array
    {
        $query = $budget->lines()->with(['account', 'costCenter']);

        if (! empty($filters['account_id'])) {
            $query->where('account_id', $filters['account_id']);
        }

        if (! empty($filters['cost_center_id'])) {
            $query->where('cost_center_id', $filters['cost_center_id']);
        }

        if (! empty($filters['period_start'])) {
            $query->whereDate('period_start', '>=', $filters['period_start']);
        }

        if (! empty($filters['period_end'])) {
            $query->whereDate('period_end', '<=', $filters['period_end']);
        }

        $lines = $query->get();

        if ($lines->isEmpty()) {
            return $this->formatEmptyReport($budget);
        }

        $costCenterTrees = $this->resolveCostCenterTrees($lines);

        $reportLines = [];
        $expensePlanned = 0.0;
        $expenseActual = 0.0;
        $revenuePlanned = 0.0;
        $revenueActual = 0.0;

        foreach ($lines as $line) {
            $costCenterIds = $line->cost_center_id ? ($costCenterTrees[$line->cost_center_id] ?? [$line->cost_center_id]) : [];
            $actual = $this->calculateActualAmount($line, $costCenterIds);
            $planned = (float) $line->planned_amount;

            $accountType = $line->account?->type ?? AccountType::EXPENSE;
            $variance = $this->calculateVariance($accountType, $planned, $actual);
            $burnRate = $planned > 0 ? round(($actual / $planned) * 100, 2) : 0.0;
            $isOverBudget = $accountType === AccountType::EXPENSE ? ($actual > $planned) : ($actual < $planned);

            if ($accountType === AccountType::EXPENSE) {
                $expensePlanned += $planned;
                $expenseActual += $actual;
            } elseif ($accountType === AccountType::REVENUE) {
                $revenuePlanned += $planned;
                $revenueActual += $actual;
            }

            $remaining = round($planned - $actual, 4);

            $reportLines[] = [
                'line_id' => $line->id,
                'account' => $line->account ? [
                    'id' => $line->account->id,
                    'code' => $line->account->code,
                    'name' => $line->account->name,
                    'type' => $accountType->value,
                ] : null,
                'cost_center' => $line->costCenter ? [
                    'id' => $line->costCenter->id,
                    'code' => $line->costCenter->code,
                    'name' => $line->costCenter->name,
                ] : null,
                'period_start' => $line->period_start?->format('Y-m-d'),
                'period_end' => $line->period_end?->format('Y-m-d'),
                'planned_amount' => $planned,
                'actual_amount' => $actual,
                'remaining_amount' => $remaining >= 0 ? $remaining : 0.0,
                'variance' => $variance,
                'burn_rate_percentage' => $burnRate,
                'is_over_budget' => $isOverBudget,
                'notes' => $line->notes,
            ];
        }

        $expenseVariance = round($expensePlanned - $expenseActual, 4);
        $expenseBurnRate = $expensePlanned > 0 ? round(($expenseActual / $expensePlanned) * 100, 2) : 0.0;

        $revenueVariance = round($revenueActual - $revenuePlanned, 4);
        $revenueAchievementRate = $revenuePlanned > 0 ? round(($revenueActual / $revenuePlanned) * 100, 2) : 0.0;

        return [
            'budget' => [
                'id' => $budget->id,
                'name' => $budget->name,
                'status' => $budget->status->value,
                'control_mode' => $budget->control_mode->value,
                'start_date' => $budget->start_date?->format('Y-m-d'),
                'end_date' => $budget->end_date?->format('Y-m-d'),
            ],
            'summary' => [
                'expenses' => [
                    'planned' => $expensePlanned,
                    'actual' => $expenseActual,
                    'variance' => $expenseVariance,
                    'burn_rate_percentage' => $expenseBurnRate,
                ],
                'revenues' => [
                    'planned' => $revenuePlanned,
                    'actual' => $revenueActual,
                    'variance' => $revenueVariance,
                    'achievement_rate_percentage' => $revenueAchievementRate,
                ],
                'net_planned' => round($revenuePlanned - $expensePlanned, 4),
                'net_actual' => round($revenueActual - $expenseActual, 4),
            ],
            'lines' => $reportLines,
        ];
    }

    /**
     * تجهيز شجرة مراكز التكلفة المحددة في البنود مسبقاً لمنع استعلامات N+1
     */
    private function resolveCostCenterTrees(Collection $lines): array
    {
        $costCenterIds = $lines->pluck('cost_center_id')->filter()->unique()->values()->toArray();
        if (empty($costCenterIds)) {
            return [];
        }

        $costCenters = CostCenter::whereIn('id', $costCenterIds)->get();
        $trees = [];

        foreach ($costCenters as $center) {
            $descendants = method_exists($center, 'descendants')
                ? $center->descendants()->pluck('id')->toArray()
                : [];

            $trees[$center->id] = array_merge([$center->id], $descendants);
        }

        return $trees;
    }

    /**
     * احتساب الفعلي لحظياً من القيود المرحلة
     */
    private function calculateActualAmount(BudgetLine $line, array $costCenterIds = []): float
    {
        $query = JournalEntryDetail::query()
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->whereNull('journal_entries.deleted_at')
            ->where('journal_entries.status', EntryStatus::Posted->value)
            ->whereBetween('journal_entries.date', [
                $line->period_start->format('Y-m-d'),
                $line->period_end->format('Y-m-d'),
            ]);

        if ($line->account_id) {
            $query->where('journal_entry_details.account_id', $line->account_id);
        } else {
            $query->join('accounts', 'journal_entry_details.account_id', '=', 'accounts.id')
                ->where('accounts.type', AccountType::EXPENSE->value);
        }

        if (! empty($costCenterIds)) {
            $query->whereIn('journal_entry_details.cost_center_id', $costCenterIds);
        }

        $sums = $query->selectRaw('SUM(journal_entry_details.debit) as total_debit, SUM(journal_entry_details.credit) as total_credit')->first();

        $debit = (float) ($sums->total_debit ?? 0);
        $credit = (float) ($sums->total_credit ?? 0);

        $accountType = $line->account?->type ?? AccountType::EXPENSE;

        if ($accountType === AccountType::EXPENSE) {
            return round($debit - $credit, 4);
        }

        return round($credit - $debit, 4);
    }

    /**
     * احتساب الانحراف المالي
     */
    private function calculateVariance(?AccountType $type, float $planned, float $actual): float
    {
        if ($type === AccountType::EXPENSE) {
            return round($planned - $actual, 4);
        }

        return round($actual - $planned, 4);
    }

    /**
     * تجهيز استجابة فارغة في حال عدم وجود بنود
     */
    private function formatEmptyReport(Budget $budget): array
    {
        return [
            'budget' => [
                'id' => $budget->id,
                'name' => $budget->name,
                'status' => $budget->status->value,
                'control_mode' => $budget->control_mode->value,
                'start_date' => $budget->start_date?->format('Y-m-d'),
                'end_date' => $budget->end_date?->format('Y-m-d'),
            ],
            'summary' => [
                'expenses' => [
                    'planned' => 0.0,
                    'actual' => 0.0,
                    'variance' => 0.0,
                    'burn_rate_percentage' => 0.0,
                ],
                'revenues' => [
                    'planned' => 0.0,
                    'actual' => 0.0,
                    'variance' => 0.0,
                    'achievement_rate_percentage' => 0.0,
                ],
                'net_planned' => 0.0,
                'net_actual' => 0.0,
            ],
            'lines' => [],
        ];
    }
}