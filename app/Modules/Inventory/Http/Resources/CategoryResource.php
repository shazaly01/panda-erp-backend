<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
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
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'parent' => new self($this->whenLoaded('parent')),
            'children' => self::collection($this->whenLoaded('children')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
