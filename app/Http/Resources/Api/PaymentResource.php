<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'payment_date' => $this->payment_date,
            'notes' => $this->notes,
            // تضمين بيانات المشروع المرتبط بالدفعة (إذا تم تحميلها)
            'project' => ProjectResource::make($this->whenLoaded('project')),
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
