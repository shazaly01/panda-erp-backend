<?php

namespace App\Modules\HR\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type, // administration, department...
            'type_label' => $this->type->label(),
            'parent_id' => $this->parent_id,
            'parent_name' => $this->parent ? $this->parent->name : null,
            'description' => $this->description,
            'is_active' => $this->is_active,
            // لعرض الشجرة، قد نحتاج لتحميل الأبناء
            'children' => DepartmentResource::collection($this->whenLoaded('children')),
        ];
    }
}
