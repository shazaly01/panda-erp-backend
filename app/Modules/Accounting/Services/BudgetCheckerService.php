<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Accounting\Enums\BudgetControlMode;
use App\Modules\Accounting\Enums\BudgetStatus;
use App\Modules\Accounting\Enums\EntryStatus;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Budget;
use App\Modules\Accounting\Models\BudgetLine;
use App\Modules\Accounting\Models\CostCenter;
use App\Modules\Accounting\Models\JournalEntryDetail;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class BudgetCheckerService
{
    /**
     * فحص مجموعة من أسطر القيود قبل الترحيل وتطبيق نمط الرقابة
     *
     * @param array<int, array{account_id: int, cost_center_id?: int|null, debit?: float|numeric-string, credit?: float|numeric-string}> $entryLines
     * @param string $entryDate
     * @return array<int, string> قائمة التحذيرات في حال كان نمط الرقابة Warning
     * @throws ValidationException في حال كان نمط الرقابة StrictStop وتجاوز المبلغ
     */
    public function validateEntryLines(array $entryLines, string $entryDate): array
    {
        $parsedDate = Carbon::parse($entryDate)->format('Y-m-d');

        $activeBudgets = Budget::query()
            ->whereIn('status', [BudgetStatus::Approved, BudgetStatus::Active])
            ->whereDate('start_date', '<=', $parsedDate)
            ->whereDate('end_date', '>=', $parsedDate)
            ->with(['lines.account', 'lines.costCenter'])
            ->get();

        if ($activeBudgets->isEmpty()) {
            return [];
        }

        $warnings = [];

        foreach ($activeBudgets as $budget) {
            if ($budget->control_mode === BudgetControlMode::Advisory) {
                continue;
            }

            foreach ($entryLines as $line) {
                $accountId = (int) ($line['account_id'] ?? 0);
                $costCenterId = isset($line['cost_center_id']) && $line['cost_center_id'] !== '' ? (int) $line['cost_center_id'] : null;
                $debit = (float) ($line['debit'] ?? 0);
                $credit = (float) ($line['credit'] ?? 0);

                $impactAmount = $debit - $credit;
                if ($impactAmount <= 0) {
                    continue;
                }

                $budgetLine = $this->findMatchingBudgetLine($budget, $accountId, $costCenterId, $parsedDate);

                if (! $budgetLine) {
                    continue;
                }

                $account = $budgetLine->account ?? Account::find($accountId);
                if (! $account || $account->type !== AccountType::EXPENSE) {
                    continue;
                }

                $currentActual = $this->calculateConsumedAmount($budgetLine);
                $projectedTotal = $currentActual + $impactAmount;
                $plannedAmount = (float) $budgetLine->planned_amount;

                if ($projectedTotal > $plannedAmount) {
                    $overAmount = round($projectedTotal - $plannedAmount, 2);

                    if ($budgetLine->account) {
                        $targetName = "الحساب ({$budgetLine->account->name})";
                    } elseif ($budgetLine->costCenter) {
                        $targetName = "مركز التكلفة ({$budgetLine->costCenter->name})";
                    } else {
                        $targetName = "البند";
                    }

                    $message = "تجاوز في موازنة {$targetName} بمقدار {$overAmount}. المعتمد: {$plannedAmount}، الفعلي بعد القيد: {$projectedTotal}.";

                    if ($budget->control_mode === BudgetControlMode::StrictStop) {
                        throw ValidationException::withMessages([
                            'budget' => [$message],
                        ]);
                    }

                    if ($budget->control_mode === BudgetControlMode::Warning) {
                        $warnings[] = $message;
                    }
                }
            }
        }

        return $warnings;
    }

    /**
     * إيجاد بند الموازنة المطابق وفق الأولوية:
     * 1. حساب مالي + مركز تكلفة محدد
     * 2. حساب مالي عام (بدون مركز تكلفة)
     * 3. موازنة مجمعة لمركز التكلفة (بدون تحديد حساب)
     */
    private function findMatchingBudgetLine(Budget $budget, int $accountId, ?int $costCenterId, string $date): ?BudgetLine
    {
        $validLines = $budget->lines->filter(function (BudgetLine $line) use ($date) {
            return $line->period_start->format('Y-m-d') <= $date && $line->period_end->format('Y-m-d') >= $date;
        });

        if ($costCenterId) {
            $line = $validLines->first(fn (BudgetLine $l) => $l->account_id === $accountId && $l->cost_center_id === $costCenterId);
            if ($line) {
                return $line;
            }
        }

        $line = $validLines->first(fn (BudgetLine $l) => $l->account_id === $accountId && is_null($l->cost_center_id));
        if ($line) {
            return $line;
        }

        if ($costCenterId) {
            $line = $validLines->first(function (BudgetLine $l) use ($costCenterId) {
                if (! is_null($l->account_id) || is_null($l->cost_center_id)) {
                    return false;
                }

                if ($l->cost_center_id === $costCenterId) {
                    return true;
                }

                $center = $l->costCenter ?? CostCenter::find($l->cost_center_id);
                if ($center && method_exists($center, 'descendants')) {
                    return $center->descendants()->where('id', $costCenterId)->exists();
                }

                return false;
            });

            if ($line) {
                return $line;
            }
        }

        return null;
    }

    /**
     * احتساب المبلغ المستهلك فعلياً حتى اللحظة للبند المحدد
     */
    private function calculateConsumedAmount(BudgetLine $line): float
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

        if ($line->cost_center_id) {
            $costCenter = $line->costCenter ?? CostCenter::find($line->cost_center_id);
            if ($costCenter && method_exists($costCenter, 'descendants')) {
                $costCenterIds = $costCenter->descendants()->pluck('id')->prepend($costCenter->id)->toArray();
                $query->whereIn('journal_entry_details.cost_center_id', $costCenterIds);
            } else {
                $query->where('journal_entry_details.cost_center_id', $line->cost_center_id);
            }
        }

        $sums = $query->selectRaw('SUM(journal_entry_details.debit) as total_debit, SUM(journal_entry_details.credit) as total_credit')->first();

        $debit = (float) ($sums->total_debit ?? 0);
        $credit = (float) ($sums->total_credit ?? 0);

        return round($debit - $credit, 4);
    }
}