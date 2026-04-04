<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FiscalYearResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'start_date' => $this->start_date->format('Y-m-d'),
            'end_date' => $this->end_date->format('Y-m-d'),

            'status' => $this->status,
            'status_label' => $this->status->label(),

            'created_by' => $this->created_by,
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
