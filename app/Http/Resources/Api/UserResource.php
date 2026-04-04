<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'username' => $this->username,
            'email' => $this->email,

            // --- [إرجاع الافتراضيات الذكية للواجهة] ---
            'default_cost_center_id'  => $this->default_cost_center_id,
            'default_box_id'          => $this->default_box_id,
            'default_bank_account_id' => $this->default_bank_account_id,

            'created_at' => $this->created_at->toDateTimeString(),

            'roles' => RoleResource::collection($this->whenLoaded('roles')),
        ];
    }
}
