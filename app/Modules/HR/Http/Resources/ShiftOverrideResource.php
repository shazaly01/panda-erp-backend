<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftOverrideResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            // دمج الاسم الأول والأخير للموظف إذا تم تحميل العلاقة
            'employee_name' => $this->whenLoaded('employee', function () {
                return trim($this->employee->first_name . ' ' . $this->employee->last_name);
            }),

            'date' => $this->date?->format('Y-m-d'),

            'original_shift_id' => $this->original_shift_id,
            'original_shift_name' => $this->whenLoaded('originalShift', function () {
                return $this->originalShift->name;
            }),

            'new_shift_id' => $this->new_shift_id,
            'new_shift_name' => $this->whenLoaded('newShift', function () {
                return $this->newShift->name;
            }),

            'reason' => $this->reason,

            'approved_by' => $this->approved_by,
            'approver_name' => $this->whenLoaded('approver', function () {
                return $this->approver->name;
            }),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
