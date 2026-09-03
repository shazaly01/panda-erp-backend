<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Modules\Inventory\Http\Resources\ProductResource;

class PriceListItemResource extends JsonResource
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
            'price_list_id' => $this->price_list_id,
            'product_id' => $this->product_id,
            'product_unit_id' => $this->product_unit_id,
            'price' => (float) $this->price,
            'min_quantity' => (float) $this->min_quantity,
            'product' => new ProductResource($this->whenLoaded('product')),
            'product_unit' => $this->whenLoaded('productUnit'),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
