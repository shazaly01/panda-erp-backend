<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OvertimePolicyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                          => $this->id,
            'name'                        => $this->name,
            'overtime_source'             => $this->overtime_source ?? 'punch',
            'working_days_per_month'      => $this->working_days_per_month,
            'working_hours_per_day'       => $this->working_hours_per_day,
            'regular_rate'                => (float) $this->regular_rate,
            'weekend_rate'                => (float) $this->weekend_rate,
            'holiday_rate'                => (float) $this->holiday_rate,
            'is_daily_basis'              => (bool) $this->is_daily_basis,
            'hours_to_day_threshold'      => $this->hours_to_day_threshold,
            'wage_factor'                 => (float) ($this->wage_factor ?? 1.0),
            'fixed_monthly_hours'         => $this->fixed_monthly_hours !== null ? (int) $this->fixed_monthly_hours : null,
            'tax_rate'                    => (float) ($this->tax_rate ?? 0.0),
            'tax_exempt_employment_types' => $this->tax_exempt_employment_types ?? [],
            'created_at'                  => $this->created_at?->toDateTimeString(),
        ];
    }
}