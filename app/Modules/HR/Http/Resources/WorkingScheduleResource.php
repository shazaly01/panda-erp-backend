<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkingScheduleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'cycle_days' => $this->cycle_days,
            'lines' => WorkingScheduleLineResource::collection($this->whenLoaded('lines')),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
