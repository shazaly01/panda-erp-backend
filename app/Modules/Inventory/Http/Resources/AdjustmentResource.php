<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdjustmentResource extends JsonResource
{
    /**
     * تحويل المورد إلى مصفوفة بيانات JSON.
     * تم إزالة حقل reason غير الموجود في مخطط القاعدة
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'adjustment_number' => $this->adjustment_number,
            'warehouse_id' => $this->warehouse_id,
            'type' => $this->type,
            'status' => $this->status,
            'adjustment_date' => $this->adjustment_date?->toDateString(),
            'notes' => $this->notes,
            'warehouse' => new WarehouseResource($this->whenLoaded('warehouse')),
            'items' => AdjustmentItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}