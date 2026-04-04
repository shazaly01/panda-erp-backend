<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'name' => $this->name,
        'url' => $this->url,
        'documentable_id' => $this->documentable_id,
        'target_info' => $this->whenLoaded('documentable'),
        'created_at' => $this->created_at->toDateTimeString(),
    ];
}
}
