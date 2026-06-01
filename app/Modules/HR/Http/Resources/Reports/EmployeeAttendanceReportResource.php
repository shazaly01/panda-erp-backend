<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Resources\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeAttendanceReportResource extends JsonResource
{
    /**
     * تحويل البيانات المجمعة القادمة من المتحكم إلى تنسيق مناسب للـ API
     */
    public function toArray(Request $request): array
    {
        $workMinutes = (int) $this->total_work_minutes;

        return [
            'employee_id'     => $this->employee_id,
            'employee_number' => $this->employee_number,
            'full_name'       => $this->full_name,
            'department_name' => $this->department_name ?? 'غير محدد',
            'summary' => [
                'present_days' => (int) $this->present_days,
                'late_days'    => (int) $this->late_days,
                'absent_days'  => (int) $this->absent_days,
                'leave_days'   => (int) $this->leave_days,
            ],
            'counters' => [
                'total_delay_minutes'       => (int) $this->total_delay_minutes,
                'total_early_leave_minutes' => (int) $this->total_early_leave_minutes,
                'total_overtime_minutes'    => (int) $this->total_overtime_minutes,
                'total_work_minutes'        => $workMinutes,
            ],
            'hours' => [
                'total_work_hours_decimal'   => round($workMinutes / 60, 2),
                'total_work_hours_formatted' => $this->formatMinutesToHours($workMinutes),
                'total_delay_formatted'      => $this->formatMinutesToHours((int) $this->total_delay_minutes),
            ]
        ];
    }

    /**
     * دالة مساعدة لتحويل الدقائق التراكمية إلى صيغة نصية مقروءة
     */
    private function formatMinutesToHours(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0 دقيقة';
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($hours > 0 && $remainingMinutes > 0) {
            return $hours . ' ساعة و ' . $remainingMinutes . ' دقيقة';
        }

        if ($hours > 0) {
            return $hours . ' ساعة';
        }

        return $remainingMinutes . ' دقيقة';
    }
}
