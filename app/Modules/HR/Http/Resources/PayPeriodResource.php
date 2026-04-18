<?php

namespace App\Modules\HR\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayPeriodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pay_group_id' => $this->pay_group_id,

            'pay_group' => $this->whenLoaded('payGroup', function() {
                return [
                    'id' => $this->payGroup->id,
                    'name' => $this->payGroup->name,
                ];
            }),

            'name' => $this->name,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'status' => $this->status, // open, processing, closed
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
