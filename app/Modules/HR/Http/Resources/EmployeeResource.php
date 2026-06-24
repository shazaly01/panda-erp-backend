<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    /**
     * تحويل الموديل شامل الحقول الأكاديمية والمستحدثة للمتدربين
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'employee_number' => $this->employee_number,
            'barcode' => $this->barcode,
            'national_id' => $this->national_id,

            // البيانات الديموغرافية
            'gender' => $this->gender,
            'gender_label' => $this->gender?->label(),

            'marital_status' => $this->marital_status,
            'marital_status_label' => $this->marital_status?->label(),

            // بيانات الوظيفة والإدارة
            'employment_type' => $this->employment_type,
            'employment_type_label' => $this->employment_type?->label(),

            'department_id' => $this->department_id,
            'position_id' => $this->position_id,
            'manager_id' => $this->manager_id,
            'user_id' => $this->user_id,

            'department' => new DepartmentResource($this->whenLoaded('department')),
            'position' => new PositionResource($this->whenLoaded('position')),
            'latest_shift' => $this->whenLoaded('latestShift'),

            // المدير المباشر / الموجه الأكاديمي
            'manager' => $this->whenLoaded('manager', function () {
                return [
                    'id' => $this->manager->id,
                    'full_name' => $this->manager->full_name,
                ];
            }),

            'status' => $this->status,
            'status_label' => $this->status?->label(),
            'join_date' => $this->join_date?->format('Y-m-d'),

            // البيانات الشخصية والأكاديمية المستحدثة للمتدربين
            'internship_start_date' => $this->internship_start_date?->format('Y-m-d'),
            'internship_end_date' => $this->internship_end_date?->format('Y-m-d'),
            'internship_status' => $this->internship_status,
            'academic_institution' => $this->academic_institution,
            'academic_major' => $this->academic_major,
            'required_training_hours' => $this->required_training_hours,
            'internship_notes' => $this->internship_notes,

            // حساب الأيام المتبقية على انتهاء التدريب ديناميكياً لتنبيهات الواجهة الأمامية
            'remaining_training_days' => $this->internship_end_date && $this->internship_end_date->isFuture()
                ? now()->startOfDay()->diffInDays($this->internship_end_date->startOfDay(), false)
                : 0,

            // البيانات الشخصية
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $this->user ? $this->user->avatar_url : null,

            'profile_photo' => $this->whenLoaded('profilePhoto', function () {
                return $this->profilePhoto ? ['url' => $this->profilePhoto->url] : null;
            }),

            // الوردية الحالية النشطة
            'current_shift' => $this->whenLoaded('employeeShifts', function () {
                $activeShift = $this->employeeShifts->first(function ($shift) {
                    return is_null($shift->end_date) || $shift->end_date >= now()->startOfDay();
                });
                return $activeShift ? new EmployeeShiftResource($activeShift) : null;
            }),

            // العقد والراتب (مع حماية برمجية ضد الـ Null وصلاحيات العرض)
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
