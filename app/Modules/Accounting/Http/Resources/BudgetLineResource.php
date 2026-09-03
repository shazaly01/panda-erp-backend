<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BudgetLineResource extends JsonResource
{
    /**
     * تحويل الكائن لمصفوفة JSON
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'budget_id' => $this->budget_id,
            'account_id' => $this->account_id,
            'account' => new AccountResource($this->whenLoaded('account')),
            'cost_center_id' => $this->cost_center_id,
            'cost_center' => new CostCenterResource($this->whenLoaded('costCenter')),
            'period_start' => $this->period_start?->format('Y-m-d'),
            'period_end' => $this->period_end?->format('Y-m-d'),
            'planned_amount' => (float) $this->planned_amount,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}