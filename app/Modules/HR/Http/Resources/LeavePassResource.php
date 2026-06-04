<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeavePassResource extends JsonResource
{
    /**
     * تحويل كائن الموديل إلى مصفوفة قابلة للحقن في استجابات الـ API.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'date'                => $this->date ? $this->date->toDateString() : null,
            'reason'              => $this->reason,

            // التواقيت المخططة (تم قص الثواني لتظهر نظيفة في الواجهات H:i)
            'requested_leave_at'  => $this->requested_leave_at ? substr($this->requested_leave_at, 0, 5) : null,
            'requested_return_at' => $this->requested_return_at ? substr($this->requested_return_at, 0, 5) : null,

            // الطوابع الزمنية الفعلية للبوابة الخارجيّة لأمن المنشأة
            'actual_leave_at'     => $this->actual_leave_at ? $this->actual_leave_at->toDateTimeString() : null,
            'actual_return_at'    => $this->actual_return_at ? $this->actual_return_at->toDateTimeString() : null,

            'pass_code'           => $this->pass_code,
            'status'              => $this->status,

            // تحميل بيانات الموظف طالب الإذن ديناميكياً لتجنب مشاكل الـ N+1 Queries
            'employee'            => $this->whenLoaded('employee', function () {
                return [
                    'id'              => $this->employee->id,
                    'full_name'       => $this->employee->full_name,
                    'employee_number' => $this->employee->employee_number,
                    'phone'           => $this->employee->phone,
                    'barcode'         => $this->employee->barcode,
                    'presence_status' => $this->employee->current_presence_status, // الحالة اللحظية للطوارئ
                ];
            }),

            // بيانات المعتمد (المدير / المشرف المسؤول)
            'approved_by'         => $this->whenLoaded('approvedBy', function () {
                return [
                    'id'        => $this->approvedBy->id,
                    'full_name' => $this->approvedBy->full_name,
                ];
            }),

            // بيانات حراس الأمن المسؤولين عن البوابة
            'gate_checked_out_by' => $this->whenLoaded('gateCheckedOutBy', function () {
                return [
                    'id'   => $this->gateCheckedOutBy->id,
                    'name' => $this->gateCheckedOutBy->name,
                ];
            }),

            'gate_checked_in_by'  => $this->whenLoaded('gateCheckedInBy', function () {
                return [
                    'id'   => $this->gateCheckedInBy->id,
                    'name' => $this->gateCheckedInBy->name,
                ];
            }),

            'created_at'          => $this->created_at ? $this->created_at->toDateTimeString() : null,
            'updated_at'          => $this->updated_at ? $this->updated_at->toDateTimeString() : null,
        ];
    }
}
