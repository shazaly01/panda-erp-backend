<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceLogResource extends JsonResource
{
    public function toArray($request): array
    {
        // 🌟 قراءة سياسة النظام الحالية
        $mode = env('ATTENDANCE_MODE', 'strict');

        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,

            'employee_name' => $this->whenLoaded('employee', fn() => $this->employee->full_name),
            'profile_photo' => $this->whenLoaded('employee', function () {
    return $this->employee->relationLoaded('profilePhoto') && $this->employee->profilePhoto
        ? ['url' => $this->employee->profilePhoto->url]
        : null;
}),
'avatar' => $this->whenLoaded('employee', function () {
    return $this->employee->user ? $this->employee->user->avatar_url : null;
}),
            'shift_name'    => $this->whenLoaded('shift', fn() => $this->shift->name),

            'date' => $this->date->format('Y-m-d'),

            'check_in'  => $this->check_in ? date('H:i', strtotime($this->check_in)) : null,

            // 🌟 البيانات الخام للفرونت إيند
            'check_out' => $this->check_out ? date('H:i', strtotime($this->check_out)) : null,

            // 🌟 إضافة الحقل المعماري الجديد لتوجيه واجهة Vue.js
            'attendance_mode' => $mode,

            // 🌟 حقل مساعد للـ UI (جاهز للطباعة مباشرة)
            'check_out_formatted' => $this->check_out
                ? date('H:i', strtotime($this->check_out))
                : ($mode === 'single_punch' ? 'غير مطلوب' : 'بصمة مفقودة'),

            // 🌟 تصفير الانصراف المبكر في نظام البصمة الواحدة حتى لا تظهر أرقام وهمية
            'delay_minutes'       => $this->delay_minutes,
            'early_leave_minutes' => $mode === 'single_punch' ? 0 : $this->early_leave_minutes,
            'overtime_minutes'    => $this->overtime_minutes,

            'status' => $this->status,
        ];
    }
}
