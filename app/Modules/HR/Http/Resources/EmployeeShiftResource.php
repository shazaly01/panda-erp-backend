<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeShiftResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee_name' => $this->whenLoaded('employee', fn() => $this->employee->full_name),

            'shift_id' => $this->shift_id,
            'shift_name' => $this->whenLoaded('shift', fn() => $this->shift->name),

            'start_date' => $this->start_date->format('Y-m-d'),
            'end_date' => $this->end_date ? $this->end_date->format('Y-m-d') : null,

            // نعيد المصفوفة كما هي، أو فارغة إذا كانت null
            'weekend_days' => $this->weekend_days ?? [],

            // إضافة حقل سريع لمعرفة ما إذا كانت الوردية هي الحالية (النشطة) للموظف
            'is_current' => is_null($this->end_date) || $this->end_date >= now()->startOfDay(),
        ];
    }
}
