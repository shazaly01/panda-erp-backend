<?php

namespace App\Modules\HR\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OvertimePolicyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'name'                   => $this->name,
            'working_days_per_month' => $this->working_days_per_month,
            'working_hours_per_day'  => $this->working_hours_per_day,
            'regular_rate'           => (float) $this->regular_rate,
            'weekend_rate'           => (float) $this->weekend_rate,
            'holiday_rate'           => (float) $this->holiday_rate,
            'is_daily_basis'         => (bool) $this->is_daily_basis,
            'hours_to_day_threshold' => $this->hours_to_day_threshold,
            'created_at'             => $this->created_at?->toDateTimeString(),
        ];
    }
}
