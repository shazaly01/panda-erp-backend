<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InternetVoucherResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'capacity' => $this->capacity,

            // الحالة مهيأة للواجهة الأمامية لتلوين الشارات (Badges)
            'status' => $this->status,
            'status_label' => $this->status === 'assigned' ? 'مصروف' : 'متاح',

            // بيانات الموظف (يتم تحميلها فقط إذا طلبناها لتسريع الاستعلام)
            'employee_id' => $this->employee_id,
            'employee_name' => $this->whenLoaded('employee', fn() => $this->employee->full_name),

            // تفاصيل عملية الصرف
            'attendance_log_id' => $this->attendance_log_id,
            'assigned_at' => $this->assigned_at ? $this->assigned_at->format('Y-m-d h:i A') : null,
            'expires_at' => $this->expires_at ? $this->expires_at->format('Y-m-d') : null,

            'created_at' => $this->created_at->format('Y-m-d'),
        ];
    }
}
