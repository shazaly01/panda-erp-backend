<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PriceListResource extends JsonResource
{
    /**
     * تحويل المورد إلى مصفوفة بيانات JSON.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'currency' => $this->currency,
            'is_active' => (bool) $this->is_active,
            'is_default' => (bool) $this->is_default,
            'prices' => PriceListItemResource::collection($this->whenLoaded('prices')),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
