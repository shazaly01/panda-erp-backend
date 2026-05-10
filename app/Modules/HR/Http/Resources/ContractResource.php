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
            'employee_id' => $this->employee_id,

            'employee' => $this->whenLoaded('employee', function() {
                return [
                    'id' => $this->employee->id,
                    'full_name' => $this->employee->full_name,
                    'employee_number' => $this->employee->employee_number,
                ];
            }),

            'basic_salary' => $this->basic_salary,

            'salary_structure' => $this->whenLoaded('salaryStructure', function() {
                return [
                    'id' => $this->salaryStructure->id,
                    'name' => $this->salaryStructure->name
                ];
            }),

            'overtime_policy' => new OvertimePolicyResource($this->whenLoaded('overtimePolicy')),

            'pay_group_id' => $this->pay_group_id,
            'pay_group' => $this->whenLoaded('payGroup', function() {
                return [
                    'id' => $this->payGroup->id,
                    'name' => $this->payGroup->name,
                    'frequency' => $this->payGroup->frequency?->value ?? $this->payGroup->frequency,
                ];
            }),

            // 🌟 الإضافة الجديدة: ربط قالب الجدولة (Working Schedule)
            'working_schedule_id' => $this->working_schedule_id,
            'working_schedule' => $this->whenLoaded('workingSchedule', function() {
                return [
                    'id' => $this->workingSchedule->id,
                    'name' => $this->workingSchedule->name,
                ];
            }),

            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'is_active' => $this->is_active,
            'attachment_url' => $this->attachment_path ? url('storage/'.$this->attachment_path) : null,
        ];
    }
}
