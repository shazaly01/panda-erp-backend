<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SequenceResource extends JsonResource
{
    /**
     * تحويل المورد إلى مصفوفة (Array).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'model'           => $this->model,
            'branch_id'       => $this->branch_id,
            'format'          => $this->format,
            'reset_frequency' => $this->reset_frequency,
            'next_value'      => $this->next_value,
            'current_year'    => $this->current_year,
            'current_month'   => $this->current_month,
            // تنسيق التواريخ بشكل قياسي للواجهات
            'created_at'      => $this->created_at ? $this->created_at->toDateTimeString() : null,
            'updated_at'      => $this->updated_at ? $this->updated_at->toDateTimeString() : null,
        ];
    }
}
