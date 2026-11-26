<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Requests\Disciplinary;

use App\Modules\HR\Models\ViolationEscalationRule;
use App\Modules\HR\Models\ViolationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ViolationTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * توحيد القيم الافتراضية للمخالفات اليدوية قبل التحقق
     */
    protected function prepareForValidation(): void
    {
        $detectionMode = $this->input('detection_mode', ViolationType::DETECTION_MANUAL);

        if ($detectionMode === ViolationType::DETECTION_MANUAL) {
            $this->merge([
                'trigger_mode'          => ViolationType::TRIGGER_PER_INCIDENT,
                'threshold_minutes'     => 0,
                'max_threshold_minutes' => null,
            ]);
        }
    }

    public function rules(): array
    {
        $violationType = $this->route('violation_type');
        $violationTypeId = is_object($violationType) ? $violationType->id : $violationType;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('hr_violation_types', 'code')->ignore($violationTypeId),
            ],
            'description' => ['nullable', 'string', 'max:2000'],

            'detection_mode' => [
                'required',
                'string',
                Rule::in([
                    ViolationType::DETECTION_MANUAL,
                    ViolationType::DETECTION_ATTENDANCE_LATE,
                    ViolationType::DETECTION_ATTENDANCE_EARLY_LEAVE,
                    ViolationType::DETECTION_ATTENDANCE_ABSENT,
                ]),
            ],

            'trigger_mode' => [
                'required',
                'string',
                Rule::in([
                    ViolationType::TRIGGER_PER_INCIDENT,
                    ViolationType::TRIGGER_CUMULATIVE_MONTHLY,
                ]),
            ],

            'threshold_minutes' => ['required', 'integer', 'min:0', 'max:10000'],
            'max_threshold_minutes' => [
                'nullable',
                'integer',
                'gt:threshold_minutes',
                'max:10000',
            ],

            'requires_approval' => ['boolean'],
            'is_active'         => ['boolean'],

            // درجات سلم التدرج العقابي
            'steps'                  => ['required', 'array', 'min:1'],
            'steps.*.step_order'     => ['required', 'integer', 'min:1', 'distinct'],
            'steps.*.action_type'    => [
                'required',
                'string',
                Rule::in([
                    ViolationEscalationRule::ACTION_VERBAL_WARNING,
                    ViolationEscalationRule::ACTION_WRITTEN_WARNING,
                    ViolationEscalationRule::ACTION_SALARY_DEDUCTION,
                    ViolationEscalationRule::ACTION_SUSPENSION,
                    ViolationEscalationRule::ACTION_TERMINATION,
                ]),
            ],
            'steps.*.deduction_type' => [
                'required',
                'string',
                Rule::in([
                    ViolationEscalationRule::DEDUCTION_NONE,
                    ViolationEscalationRule::DEDUCTION_DAYS,
                    ViolationEscalationRule::DEDUCTION_FIXED,
                    ViolationEscalationRule::DEDUCTION_PERCENTAGE,
                ]),
            ],
            'steps.*.deduction_value' => ['nullable', 'numeric', 'min:0'],
            'steps.*.suspension_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'steps.*.validity_months' => ['required', 'integer', 'min:1', 'max:60'],
            'steps.*.notes'           => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * التحقق المنطقي المتقاطع لدرجات التدرج العقابي
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $steps = $this->input('steps', []);
            if (!is_array($steps)) {
                return;
            }

            foreach ($steps as $index => $step) {
                $stepNumber = $index + 1;
                $actionType = $step['action_type'] ?? null;
                $deductionType = $step['deduction_type'] ?? ViolationEscalationRule::DEDUCTION_NONE;
                $deductionValue = (float) ($step['deduction_value'] ?? 0);
                $suspensionDays = (int) ($step['suspension_days'] ?? 0);

                if ($actionType === ViolationEscalationRule::ACTION_SALARY_DEDUCTION) {
                    if ($deductionType === ViolationEscalationRule::DEDUCTION_NONE || $deductionValue <= 0) {
                        $validator->errors()->add(
                            "steps.{$index}.deduction_value",
                            "يجب تحديد نوع وقيمة الخصم المالي أكبر من صفر في الدرجة رقم ({$stepNumber})."
                        );
                    }
                }

                if ($actionType === ViolationEscalationRule::ACTION_SUSPENSION && $suspensionDays < 1) {
                    $validator->errors()->add(
                        "steps.{$index}.suspension_days",
                        "يجب تحديد عدد أيام الإيقاف المؤقت (يوم واحد على الأقل) في الدرجة رقم ({$stepNumber})."
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required'                        => 'اسم المخالفة أو اللائحة مطلوب.',
            'code.required'                        => 'كود المخالفة مطلوب.',
            'code.unique'                          => 'كود المخالفة مستخدم مسبقاً.',
            'max_threshold_minutes.gt'             => 'الحد الأقصى للدقائق يجب أن يكون أكبر من الحد الأدنى.',
            'steps.required'                       => 'يجب إضافة درجة واحدة على الأقل في سلم التدرج العقابي.',
            'steps.min'                            => 'يجب إضافة درجة واحدة على الأقل في سلم التدرج العقابي.',
            'steps.*.step_order.distinct'          => 'لا يمكن تكرار رقم الترتيب في درجات سلم التدرج العقابي.',
            'steps.*.validity_months.required'     => 'يجب تحديد مدة سريان الجزاء بالأشهر قبل سقوطه بالتقادم.',
            'steps.*.validity_months.min'          => 'مدة سريان الجزاء يجب ألا تقل عن شهر واحد.',
        ];
    }
}