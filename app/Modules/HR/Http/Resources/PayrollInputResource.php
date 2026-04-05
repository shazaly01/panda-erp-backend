<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PayrollInputResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee_name' => $this->whenLoaded('employee', fn() => $this->employee->full_name),

            'type'   => $this->type,
            'amount' => (float) $this->amount, // ضمان إرجاع المبلغ كرقم عشري

            'date'   => $this->date->format('Y-m-d'),
            'reason' => $this->reason,

            // حالة المعالجة (مهمة جداً للواجهة الأمامية لإخفاء زر التعديل إذا كانت true)
            'is_processed' => (bool) $this->is_processed,

            // إظهار رقم مسير الرواتب فقط إذا تم الترحيل
            'payroll_batch_id' => $this->whenNotNull($this->payroll_batch_id),

            'created_by_name' => $this->whenLoaded('creator', fn() => $this->creator->name),
        ];
    }
}
