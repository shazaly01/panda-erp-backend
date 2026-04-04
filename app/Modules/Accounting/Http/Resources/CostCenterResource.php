<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CostCenterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,


            'parent_id' => $this->parent_id,
            'is_active' => $this->is_active,
            'notes' => $this->notes,

            // دعم العرض الشجري
            'children' => CostCenterResource::collection($this->whenLoaded('children')),

            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
