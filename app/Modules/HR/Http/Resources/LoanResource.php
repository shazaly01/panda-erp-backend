<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LoanResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee_name' => $this->whenLoaded('employee', fn() => $this->employee->full_name),

            'amount' => (float) $this->amount,
            'reason' => $this->reason,
            'voucher_id' => $this->whenNotNull($this->voucher_id),

            'deduction_start_date' => $this->deduction_start_date->format('Y-m-d'),
            'installments_count' => $this->installments_count,

            'estimated_installment' => round($this->amount / $this->installments_count, 2),

            'status' => $this->status,
            'approved_by_name' => $this->whenLoaded('approver', fn() => $this->approver->name),

            // =========== أضف هذا السطر هنا ===========
            'installments' => $this->whenLoaded('installments'),
            // =========================================

            'requested_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
