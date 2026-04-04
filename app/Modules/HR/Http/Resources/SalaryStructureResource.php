<?php

namespace App\Modules\HR\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalaryStructureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,

            // إرجاع القواعد المرتبطة باستخدام الـ Resource الخاص بها
            // نستخدم whenLoaded لتجنب تحميل البيانات إذا لم نطلبها
            'rules' => SalaryRuleResource::collection($this->whenLoaded('rules')),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
