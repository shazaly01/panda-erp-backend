<?php

namespace App\Modules\HR\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'employee_number' => $this->employee_number,
            'barcode' => $this->barcode,
            'national_id' => $this->national_id,

            // البيانات الديموغرافية (مع إضافة الـ Labels للفرونت إند)
            'gender' => $this->gender,
            'gender_label' => $this->gender?->label(),

            'marital_status' => $this->marital_status,
            'marital_status_label' => $this->marital_status?->label(),

            // بيانات الوظيفة والإدارة
            'employment_type' => $this->employment_type,
            'employment_type_label' => $this->employment_type?->label(),

            'department' => new DepartmentResource($this->whenLoaded('department')),
            'position' => new PositionResource($this->whenLoaded('position')),
            'latest_shift' => $this->whenLoaded('latestShift'),

            // المدير المباشر
            'manager' => $this->whenLoaded('manager', function () {
                return [
                    'id' => $this->manager->id,
                    'full_name' => $this->manager->full_name,
                ];
            }),

            'status' => $this->status,
            'status_label' => $this->status?->label(),
            'join_date' => $this->join_date?->format('Y-m-d'),

            // البيانات الشخصية
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $this->user ? $this->user->avatar_url : null,

            // الوردية الحالية النشطة
            'current_shift' => $this->whenLoaded('employeeShifts', function () {
                $activeShift = $this->employeeShifts->first(function ($shift) {
                    return is_null($shift->end_date) || $shift->end_date >= now()->startOfDay();
                });
                return $activeShift ? new EmployeeShiftResource($activeShift) : null;
            }),

            // العقد والراتب (مع حماية برمجية ضد الـ Null)
            'current_contract' => $this->when(
                $this->relationLoaded('currentContract') &&
                $this->currentContract &&
                $request->user()->can('view', $this->currentContract),
                function () {
                    return new ContractResource($this->currentContract);
                }
            ),

            // معلومات النظام
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
