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
            'national_id' => $this->national_id,

            // البيانات الديموغرافية
            'gender' => $this->gender,
            'marital_status' => $this->marital_status,

            // بيانات الوظيفة والإدارة
            'employment_type' => $this->employment_type,
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'position' => new PositionResource($this->whenLoaded('position')),

            // المدير المباشر (نحمل الاسم والـ ID فقط لتخفيف الاستجابة)
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

            // الوردية الحالية النشطة (تفيدنا جداً في شاشة الملف الشخصي)
            'current_shift' => $this->whenLoaded('employeeShifts', function () {
                $activeShift = $this->employeeShifts->first(function ($shift) {
                    return is_null($shift->end_date) || $shift->end_date >= now()->startOfDay();
                });
                return $activeShift ? new EmployeeShiftResource($activeShift) : null;
            }),

            // العقد والراتب (محمي بالصلاحيات)
            'current_contract' => $this->when(
                $request->user()->can('view', $this->whenLoaded('currentContract')),
                new ContractResource($this->whenLoaded('currentContract'))
            ),

            // معلومات النظام
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
