<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseResource extends JsonResource
{
    /**
     * تحويل الكائن إلى مصفوفة قابلة للعرض عبر الـ API
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'phone' => $this->phone,
            'address' => $this->address,
            'manager_id' => $this->manager_id,
            'account_id' => $this->account_id,
            'is_active' => (bool) $this->is_active,
            'manager' => $this->whenLoaded('manager', function () {
                return [
                    'id' => $this->manager->id,
                    'name' => $this->manager->name,
                    'email' => $this->manager->email,
                ];
            }),
            'account' => $this->whenLoaded('account', function () {
                return [
                    'id' => $this->account->id,
                    'code' => $this->account->code,
                    'name' => $this->account->name,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}