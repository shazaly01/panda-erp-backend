<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransferResource extends JsonResource
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
            'transfer_number' => $this->transfer_number,
            'from_warehouse_id' => $this->from_warehouse_id,
            'to_warehouse_id' => $this->to_warehouse_id,
            'status' => $this->status,
            'transfer_date' => $this->transfer_date?->toDateString(),
            'notes' => $this->notes,
            'from_warehouse' => new WarehouseResource($this->whenLoaded('fromWarehouse')),
            'to_warehouse' => new WarehouseResource($this->whenLoaded('toWarehouse')),
            'items' => TransferItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
