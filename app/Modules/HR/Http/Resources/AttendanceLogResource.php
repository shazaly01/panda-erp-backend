<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceLogResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,

            // جلب العلاقات عند طلبها (Eager Loading)
            'employee_name' => $this->whenLoaded('employee', fn() => $this->employee->full_name),
            'shift_name'    => $this->whenLoaded('shift', fn() => $this->shift->name),

            'date' => $this->date->format('Y-m-d'),

            // تنسيق الوقت ليظهر كـ 08:30 بدلاً من 08:30:00
            'check_in'  => $this->check_in ? date('H:i', strtotime($this->check_in)) : null,
            'check_out' => $this->check_out ? date('H:i', strtotime($this->check_out)) : null,

            // الأرقام كما هي (دقائق)
            'delay_minutes'       => $this->delay_minutes,
            'early_leave_minutes' => $this->early_leave_minutes,
            'overtime_minutes'    => $this->overtime_minutes,

            'status' => $this->status,
        ];
    }
}
