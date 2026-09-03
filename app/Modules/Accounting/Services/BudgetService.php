<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Enums\BudgetStatus;
use App\Modules\Accounting\Models\Budget;
use App\Modules\Accounting\Models\BudgetLine;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BudgetService
{
    /**
     * جلب قائمة الموازنات مع الفلاتر والترقيم
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = Budget::query()
            ->with(['fiscalYear', 'creator', 'approver'])
            ->withCount('lines');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['fiscal_year_id'])) {
            $query->where('fiscal_year_id', $filters['fiscal_year_id']);
        }

        if (! empty($filters['control_mode'])) {
            $query->where('control_mode', $filters['control_mode']);
        }

        if (! empty($filters['search'])) {
            $query->where('name', 'like', '%' . $filters['search'] . '%');
        }

        $perPage = isset($filters['per_page']) ? (int) $filters['per_page'] : 15;

        return $query->orderByDesc('id')->paginate($perPage);
    }

    /**
     * إنشاء موازنة جديدة مع بنودها داخل Transaction
     */
    public function create(array $data, int $userId): Budget
    {
        return DB::transaction(function () use ($data, $userId) {
            $totalAmount = 0.0;
            foreach ($data['lines'] as $line) {
                $totalAmount += (float) $line['planned_amount'];
            }

            $budget = Budget::create([
                'name' => $data['name'],
                'fiscal_year_id' => $data['fiscal_year_id'] ?? null,
                'period_type' => $data['period_type'],
                'control_mode' => $data['control_mode'],
                'status' => BudgetStatus::Draft,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'total_amount' => $totalAmount,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            foreach ($data['lines'] as $lineData) {
                $budget->lines()->create([
                    'account_id' => $lineData['account_id'],
                    'cost_center_id' => $lineData['cost_center_id'] ?? null,
                    'period_start' => $lineData['period_start'],
                    'period_end' => $lineData['period_end'],
                    'planned_amount' => $lineData['planned_amount'],
                    'notes' => $lineData['notes'] ?? null,
                ]);
            }

            return $budget->load(['lines.account', 'lines.costCenter', 'fiscalYear', 'creator']);
        });
    }

    /**
     * تعديل الموازنة وبنودها
     */
    public function update(Budget $budget, array $data): Budget
    {
        if (! $budget->isDraft()) {
            abort(422, 'لا يمكن تعديل موازنة غير مسودة.');
        }

        return DB::transaction(function () use ($budget, $data) {
            $totalAmount = 0.0;
            foreach ($data['lines'] as $line) {
                $totalAmount += (float) $line['planned_amount'];
            }

            $budget->update([
                'name' => $data['name'],
                'fiscal_year_id' => $data['fiscal_year_id'] ?? null,
                'period_type' => $data['period_type'],
                'control_mode' => $data['control_mode'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'total_amount' => $totalAmount,
                'notes' => $data['notes'] ?? null,
            ]);

            $budget->lines()->delete();

            foreach ($data['lines'] as $lineData) {
                $budget->lines()->create([
                    'account_id' => $lineData['account_id'],
                    'cost_center_id' => $lineData['cost_center_id'] ?? null,
                    'period_start' => $lineData['period_start'],
                    'period_end' => $lineData['period_end'],
                    'planned_amount' => $lineData['planned_amount'],
                    'notes' => $lineData['notes'] ?? null,
                ]);
            }

            return $budget->load(['lines.account', 'lines.costCenter', 'fiscalYear', 'creator']);
        });
    }

    /**
     * حذف الموازنة
     */
    public function delete(Budget $budget): bool
    {
        if (! $budget->isDraft()) {
            abort(422, 'لا يمكن حذف موازنة غير مسودة.');
        }

        return DB::transaction(function () use ($budget) {
            $budget->lines()->delete();
            return (bool) $budget->delete();
        });
    }

    /**
     * اعتماد الموازنة
     */
    public function approve(Budget $budget, int $userId): Budget
    {
        if (! $budget->isDraft()) {
            abort(422, 'الموازنة يجب أن تكون في حالة مسودة ليتم اعتمادها.');
        }

        $budget->update([
            'status' => BudgetStatus::Approved,
            'approved_by' => $userId,
            'approved_at' => Carbon::now(),
        ]);

        return $budget->fresh(['fiscalYear', 'creator', 'approver']);
    }

    /**
     * تفعيل الموازنة المعتمدة
     */
    public function activate(Budget $budget): Budget
    {
        if (! $budget->isApproved()) {
            abort(422, 'الموازنة يجب أن تكون معتمدة أولاً قبل تفعيلها.');
        }

        $budget->update([
            'status' => BudgetStatus::Active,
        ]);

        return $budget->fresh(['fiscalYear', 'creator', 'approver']);
    }

    /**
     * إغلاق الموازنة
     */
    public function close(Budget $budget): Budget
    {
        if ($budget->status === BudgetStatus::Closed) {
            abort(422, 'الموازنة مغلقة بالفعل.');
        }

        $budget->update([
            'status' => BudgetStatus::Closed,
        ]);

        return $budget->fresh(['fiscalYear', 'creator', 'approver']);
    }
}