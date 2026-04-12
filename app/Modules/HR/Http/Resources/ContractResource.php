<?php

namespace App\Modules\HR\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // 🌟 هذا السطر هو مفتاح الربط مع الـ Dropdown في Vue
            'employee_id' => $this->employee_id,

            // 🌟 هذا الجزء هو المسؤول عن إرسال "اسم الموظف" للواجهة
            'employee' => $this->whenLoaded('employee', function() {
                return [
                    'id' => $this->employee->id,
                    'full_name' => $this->employee->full_name,
                ];
            }),

            'basic_salary' => $this->basic_salary,

            'salary_structure' => $this->whenLoaded('salaryStructure', function() {
                return [
                    'id' => $this->salaryStructure->id,
                    'name' => $this->salaryStructure->name
                ];
            }),

            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'is_active' => $this->is_active,
            'attachment_url' => $this->attachment_path ? url('storage/'.$this->attachment_path) : null,
        ];
    }
}
