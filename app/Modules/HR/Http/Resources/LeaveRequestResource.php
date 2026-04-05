<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestResource extends JsonResource
{
    /**
     * تحويل المورد إلى مصفوفة قابلة للتحويل إلى JSON
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,

            // نجلب اسم الموظف واسم نوع الإجازة فقط إذا تم تحميل العلاقات (WhenLoaded) لتجنب مشكلة N+1 Query
            'employee_name' => $this->whenLoaded('employee', fn() => $this->employee->full_name),
            'leave_type_name' => $this->whenLoaded('leaveType', fn() => $this->leaveType->name),

            'start_date' => $this->start_date->format('Y-m-d'),
            'end_date' => $this->end_date->format('Y-m-d'),

            // إرجاع الأيام كرقم عشري لدعم أنصاف الأيام (مثال: 1.5)
            'total_days' => (float) $this->total_days,

            'reason' => $this->reason,
            'status' => $this->status,

            // جلب اسم المدير الذي اعتمد الطلب إن وُجد
            'approved_by_name' => $this->whenLoaded('approver', fn() => $this->approver->name),

            // تاريخ تقديم الطلب
            'requested_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
