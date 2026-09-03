<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockBatchResource extends JsonResource
{
    /**
     * تحويل المورد إلى مصفوفة بيانات JSON.
     * تم تصحيح المسميات واستبعاد الحقول غير الموجودة في جدول الباتشات
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'batch_number' => $this->batch_number,
            'manufacturing_date' => $this->manufacturing_date?->toDateString(),
            'expiry_date' => $this->expiry_date?->toDateString(),
            'is_active' => (bool) $this->is_active,
            'product' => new ProductResource($this->whenLoaded('product')),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}