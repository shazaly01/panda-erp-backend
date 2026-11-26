<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Resources;

use App\Modules\HR\Models\ViolationEscalationRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ViolationTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'name'                  => $this->name,
            'code'                  => $this->code,
            'description'           => $this->description,
            'detection_mode'        => $this->detection_mode,
            'detection_mode_label'  => $this->detection_mode_label,
            'trigger_mode'          => $this->trigger_mode,
            'trigger_mode_label'    => $this->trigger_mode_label,
            'threshold_minutes'     => (int) $this->threshold_minutes,
            'max_threshold_minutes' => $this->max_threshold_minutes !== null
                ? (int) $this->max_threshold_minutes
                : null,
            'requires_approval'     => (bool) $this->requires_approval,
            'is_active'             => (bool) $this->is_active,
            'is_automated'          => $this->isAttendanceAutomated(),

            'steps' => $this->whenLoaded('escalationRules', function () {
                return $this->escalationRules->map(function (ViolationEscalationRule $rule): array {
                    return [
                        'id'                => $rule->id,
                        'step_order'        => (int) $rule->step_order,
                        'action_type'       => $rule->action_type,
                        'action_type_label' => $rule->action_type_label,
                        'deduction_type'    => $rule->deduction_type,
                        'deduction_value'   => (float) $rule->deduction_value,
                        'suspension_days'   => (int) $rule->suspension_days,
                        'validity_months'   => (int) $rule->validity_months,
                        'notes'             => $rule->notes,
                    ];
                })->values();
            }),

            'disciplinary_actions_count' => $this->whenCounted('disciplinaryActions'),
            'created_at'                 => $this->created_at?->toDateTimeString(),
            'updated_at'                 => $this->updated_at?->toDateTimeString(),
        ];
    }
}