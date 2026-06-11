<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'app_name' => $this['app_name'],
            'logo_full_url' => $this['logo_full_url'],
            'logo_mini_url' => $this['logo_mini_url'],
            'favicon_url' => $this['favicon_url'],
        ];
    }
}
