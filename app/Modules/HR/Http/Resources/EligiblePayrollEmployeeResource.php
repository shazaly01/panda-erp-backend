<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EligiblePayrollEmployeeResource extends JsonResource
{
    /**
     * تحويل الكائن إلى مصفوفة JSON
     */
    public function toArray(Request $request): array
    {
        $contract = $this->activeContract ?? $this->currentContract;

        return [
            'id'              => $this->id,
            'full_name'       => $this->full_name,
            'employee_number' => $this->employee_number,
            'status'          => $this->resolveStatus(),
            'basic_salary'    => $contract ? (float) $contract->basic_salary : 0.0,

            'department'      => $this->whenLoaded('department', function () {
                return [
                    'id'   => $this->department->id,
                    'name' => $this->department->name,
                ];
            }),

            'position'        => $this->whenLoaded('position', function () {
                return [
                    'id'   => $this->position->id,
                    'name' => $this->position->name,
                ];
            }),

            // حالة الترحيل المحسوبة مسبقاً للفترة المالية المحددة
            'is_processed'    => (bool) ($this->is_processed ?? false),
        ];
    }

    /**
     * استخراج قيمة الحالة بأمان
     */
    protected function resolveStatus(): mixed
    {
        if ($this->status instanceof \BackedEnum) {
            return $this->status->value;
        }

        return $this->status;
    }
}
