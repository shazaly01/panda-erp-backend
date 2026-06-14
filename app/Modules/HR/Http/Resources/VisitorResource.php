<?php

namespace App\Modules\HR\Http\Resources;

use App\Http\Resources\Api\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisitorResource extends JsonResource
{
    /**
     * تحويل كائن الموديل إلى مصفوفة قابلة للإرسال عبر الـ API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'national_id' => $this->national_id,
            'company_from' => $this->company_from,
            'purpose' => $this->purpose,
            'status' => $this->status,
            'qr_token' => $this->qr_token,

            // التواريخ والأوقات بصيغة واضحة للفرونت إند
            'checked_in_at' => $this->checked_in_at?->toIso8601String(),
            'checked_out_at' => $this->checked_out_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // ربط المعرفات الأساسية
            'employee_id' => $this->employee_id,
            'gatekeeper_id' => $this->gatekeeper_id,

            // تضمين بيانات العلاقات بشكل مشروط في حال تم عمل eager loading لها
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'gatekeeper' => new UserResource($this->whenLoaded('gatekeeper')),
        ];
    }
}
