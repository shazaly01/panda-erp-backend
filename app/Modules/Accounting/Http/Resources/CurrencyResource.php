<?php

namespace App\Modules\Accounting\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurrencyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'code'          => $this->code,
            'name'          => $this->name,
            'symbol'        => $this->symbol,
            'exchange_rate' => (float) $this->exchange_rate, // تحويله لرقم عشري
            'is_base'       => (bool) $this->is_base,
            'is_active'     => (bool) $this->is_active,
            'created_at'    => $this->created_at->toDateTimeString(),
        ];
    }
}
