<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeDisciplinaryActionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'number'             => $this->number,
            'employee_id'        => $this->employee_id,
            'violation_type_id'  => $this->violation_type_id,
            'escalation_rule_id' => $this->escalation_rule_id,
            'attendance_log_id'  => $this->attendance_log_id,
            'pay_period_id'      => $this->pay_period_id,

            // تفاصيل العقوبة والتدرج (لقطة ثابتة وقت المخالفة)
            'step_order'        => (int) $this->step_order,
            'action_type'       => $this->action_type,
            'action_type_label' => $this->action_type_label,
            'deduction_days'    => (float) $this->deduction_days,
            'deduction_amount'  => (float) $this->deduction_amount,
            'suspension_days'   => (int) $this->suspension_days,

            // التواريخ وحالة السريان والتقادم
            'violation_date'   => $this->violation_date?->format('Y-m-d'),
            'expires_at'       => $this->expires_at?->format('Y-m-d'),
            'status'           => $this->status,
            'effective_status' => $this->effective_status,
            'status_label'     => $this->status_label,
            'is_expired'       => $this->isExpiredOn(),
            'reason'           => $this->reason,

            // بيانات الاعتماد والإلغاء والتدقيق
            'approved_by'         => $this->approved_by,
            'approved_at'         => $this->approved_at?->toDateTimeString(),
            'cancelled_by'        => $this->cancelled_by,
            'cancelled_at'        => $this->cancelled_at?->toDateTimeString(),
            'cancellation_reason' => $this->cancellation_reason,
            'created_by'          => $this->created_by,
            'created_at'          => $this->created_at?->toDateTimeString(),

            // العلاقات المحمّلة
            'employee' => $this->whenLoaded('employee', function () {
                return [
                    'id'              => $this->employee->id,
                    'full_name'       => $this->employee->full_name,
                    'employee_number' => $this->employee->employee_number,
                ];
            }),

            'violation_type' => $this->whenLoaded('violationType', function () {
                return [
                    'id'             => $this->violationType->id,
                    'name'           => $this->violationType->name,
                    'code'           => $this->violationType->code,
                    'detection_mode' => $this->violationType->detection_mode,
                    'trigger_mode'   => $this->violationType->trigger_mode,
                ];
            }),

            'escalation_rule' => $this->whenLoaded('escalationRule', function () {
                return [
                    'id'              => $this->escalationRule->id,
                    'step_order'      => (int) $this->escalationRule->step_order,
                    'action_type'     => $this->escalationRule->action_type,
                    'validity_months' => (int) $this->escalationRule->validity_months,
                    'notes'           => $this->escalationRule->notes,
                ];
            }),

            'attendance_log' => $this->whenLoaded('attendanceLog', function () {
                return [
                    'id'                  => $this->attendanceLog->id,
                    'date'                => $this->attendanceLog->date?->format('Y-m-d'),
                    'check_in'            => $this->attendanceLog->check_in,
                    'check_out'           => $this->attendanceLog->check_out,
                    'delay_minutes'       => (int) ($this->attendanceLog->delay_minutes ?? 0),
                    'early_leave_minutes' => (int) ($this->attendanceLog->early_leave_minutes ?? 0),
                    'status'              => $this->attendanceLog->status,
                ];
            }),

            'pay_period' => $this->whenLoaded('payPeriod', function () {
                return [
                    'id'         => $this->payPeriod->id,
                    'name'       => $this->payPeriod->name,
                    'start_date' => $this->payPeriod->start_date?->format('Y-m-d'),
                    'end_date'   => $this->payPeriod->end_date?->format('Y-m-d'),
                ];
            }),

            'payroll_input' => $this->whenLoaded('payrollInput', function () {
                return [
                    'id'               => $this->payrollInput->id,
                    'amount'           => (float) $this->payrollInput->amount,
                    'is_processed'     => (bool) $this->payrollInput->is_processed,
                    'payroll_batch_id' => $this->payrollInput->payroll_batch_id,
                ];
            }),

            'approver' => $this->whenLoaded('approver', function () {
                return [
                    'id'   => $this->approver->id,
                    'name' => $this->approver->name,
                ];
            }),

            'canceller' => $this->whenLoaded('canceller', function () {
                return [
                    'id'   => $this->canceller->id,
                    'name' => $this->canceller->name,
                ];
            }),

            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id'   => $this->creator->id,
                    'name' => $this->creator->name,
                ];
            }),
        ];
    }
}