<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosShiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shift_number' => $this->shift_number,
            'user' => [
                'id' => $this->user_id,
                'name' => $this->user?->name,
            ],
            'branch_id' => $this->branch_id,
            'warehouse' => [
                'id' => $this->warehouse_id,
                'name' => $this->warehouse?->name,
            ],
            'status' => $this->status,
            'opening_cash' => (float) $this->opening_cash,
            'closing_cash' => $this->closing_cash !== null ? (float) $this->closing_cash : null,
            'actual_cash' => $this->actual_cash !== null ? (float) $this->actual_cash : null,
            'difference' => (float) $this->difference,
            'total_sales' => (float) $this->total_sales,
            'total_returns' => (float) $this->total_returns,
            'total_cash' => (float) $this->total_cash,
            'total_card' => (float) $this->total_card,
            'opened_at' => $this->opened_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'closed_by' => [
                'id' => $this->closed_by,
                'name' => $this->closedBy?->name,
            ],
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}