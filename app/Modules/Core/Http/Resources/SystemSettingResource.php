<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SystemSettingResource extends JsonResource
{
    /**
     * تحويل البيانات المهيكلة للإعدادات للواجهة الأمامية
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'base_currency_id' => $this->base_currency_id,
            'base_currency'    => $this->whenLoaded('baseCurrency', function () {
                return [
                    'id'            => $this->baseCurrency->id,
                    'code'          => $this->baseCurrency->code,
                    'name'          => $this->baseCurrency->name,
                    'symbol'        => $this->baseCurrency->symbol,
                    'exchange_rate' => $this->baseCurrency->exchange_rate,
                ];
            }),
            'active_modules'   => $this->active_modules ?? [],
            'company_name'     => $this->company_name,
            'updated_at'       => $this->updated_at?->toIso8601String(),
        ];
    }
}