<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'project_owner' => $this->project_owner,
            'contract_value' => (float) $this->contract_value,
            // تحويل القيمة إلى رقم عشري للتناسق
            'due_value' => (float) $this->due_value,
            'award_date' => $this->award_date,

            // === [التعديل هنا] ===
            // إضافة مجموع الدفعات.
            // whenNotNull يتحقق من أن القيمة `payments_sum_amount` تم تحميلها وحسابها.
            // إذا لم تكن موجودة (لتجنب الأخطاء)، ستكون قيمتها 0.
            'total_payments' => $this->whenNotNull($this->payments_sum_amount, (float) $this->payments_sum_amount, 0.0),

            // تضمين بيانات الشركة المرتبطة بالمشروع
            'company' => CompanyResource::make($this->whenLoaded('company')),
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
