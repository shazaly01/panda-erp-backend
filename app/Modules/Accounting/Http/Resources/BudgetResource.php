<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Api\UserResource;

class BudgetResource extends JsonResource
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
            'name' => $this->name,
            'fiscal_year_id' => $this->fiscal_year_id,
            'fiscal_year' => new FiscalYearResource($this->whenLoaded('fiscalYear')),
            'period_type' => [
                'value' => $this->period_type->value,
                'label' => $this->period_type->label(),
            ],
            'control_mode' => [
                'value' => $this->control_mode->value,
                'label' => $this->control_mode->label(),
            ],
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'total_amount' => (float) $this->total_amount,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'creator' => new UserResource($this->whenLoaded('creator')),
            'approved_by' => $this->approved_by,
            'approver' => new UserResource($this->whenLoaded('approver')),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'lines' => BudgetLineResource::collection($this->whenLoaded('lines')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}