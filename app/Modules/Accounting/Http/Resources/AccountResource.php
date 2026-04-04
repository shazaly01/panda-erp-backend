<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,

            // إرجاع قيمة الـ Enum (debit/credit)
            'nature' => $this->nature,
            // إرجاع النص العربي للعرض (مدين/دائن)
            'nature_label' => $this->nature->label(),

            'type' => $this->type,
            'currency_id' => $this->currency_id,
            'is_transactional' => $this->is_transactional,
            'requires_cost_center' => $this->requires_cost_center,
            'notes' => $this->notes,
            'parent_id' => $this->parent_id,

            // ميزة قوية: إذا قمنا بتحميل الأبناء (Eager Loading)، سيتم عرضهم بشكل شجري
            'children' => AccountResource::collection($this->whenLoaded('children')),

            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
