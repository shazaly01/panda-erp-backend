<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entry_number' => $this->entry_number, // سيكون null إذا كان مسودة
            'date' => $this->date->format('Y-m-d'),

            'status' => $this->status,
            'status_label' => $this->status->label(),

            'source' => $this->source,
            'source_label' => $this->source->label(),

            'description' => $this->description,
            'currency_id' => $this->currency_id,

            // حساب الإجماليات للعرض فقط
            'total_debit' => (float) $this->details->sum('debit'),
            'total_credit' => (float) $this->details->sum('credit'),

            'created_by' => $this->created_by,
            'posted_at' => $this->posted_at,

            // استدعاء منسق التفاصيل
            'details' => JournalEntryDetailResource::collection($this->whenLoaded('details')),

            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
