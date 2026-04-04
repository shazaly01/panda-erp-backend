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
            'full_name' => $this->full_name, // الحقل الموحد
            'employee_number' => $this->employee_number,

            // بيانات الوظيفة
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'position' => new PositionResource($this->whenLoaded('position')),
            'status' => $this->status,
            'status_label' => $this->status->label(),
            'join_date' => $this->join_date->format('Y-m-d'),

            // البيانات الشخصية
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $this->user ? $this->user->avatar_url : null, // إذا كان مربوطاً بمستخدم

            // العقد والراتب (محمي بالصلاحيات)
            'current_contract' => $this->when(
                $request->user()->can('view', $this->whenLoaded('currentContract')),
                new ContractResource($this->whenLoaded('currentContract'))
            ),
        ];
    }
}
