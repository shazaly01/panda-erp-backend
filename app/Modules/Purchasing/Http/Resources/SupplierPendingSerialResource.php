<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Http\Resources;

use App\Modules\Inventory\Models\StockSerial;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockSerial
 */
class SupplierPendingSerialResource extends JsonResource
{
    /**
     * تحويل كائن الأسطوانة إلى مصفوفة استجابة JSON
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product?->name,
            'serial_number' => $this->serial_number,
            'tare_weight' => $this->tare_weight !== null ? (float) $this->tare_weight : null,
            'gross_weight' => $this->gross_weight !== null ? (float) $this->gross_weight : null,
            'current_gas_weight' => $this->current_gas_weight !== null ? (float) $this->current_gas_weight : null,
            'status' => $this->status,
            'notes' => $this->notes,
            'dispatched_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}