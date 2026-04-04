<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'commercial_record' => $this->commercial_record,
            'tax_number' => $this->tax_number,
            'license_number' => $this->license_number, // <-- [تمت الإضافة هنا]
            'address' => $this->address,
            'owner_name' => $this->owner_name,
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
