<?php

declare(strict_types=1);

namespace App\Modules\HR\Database\Seeders;

use App\Modules\HR\Models\ViolationEscalationRule;
use App\Modules\HR\Models\ViolationType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ViolationTypeSeeder extends Seeder
{
    /**
     * تهيئة قوالب اللوائح والمخالفات وسلالم التدرج العقابي الافتراضية
     * (قابلة للتعديل بالكامل من شاشة الإعدادات حسب سياسة كل شركة)
     */
    public function run(): void
    {
        $violationTemplates = [
            // =========================================================================
            // 1. مخالفة آلية لكل حادثة: التأخير الصباحي (السلم الكامل كما في لائحة المؤسسة)
            // =========================================================================
            [
                'code'                  => 'LATE_ARRIVAL',
                'name'                  => 'التأخير الصباحي عن موعد بدء الوردية',
                'description'           => 'يُفعّل آلياً من سجل الحضور عند تجاوز دقائق التأخير الحد المسموح به بعد فترة السماح.',
                'detection_mode'        => ViolationType::DETECTION_ATTENDANCE_LATE,
                'trigger_mode'          => ViolationType::TRIGGER_PER_INCIDENT,
                'threshold_minutes'     => 15,
                'max_threshold_minutes' => null,
                'requires_approval'     => true,
                'is_active'             => true,
                'steps'                 => [
                    [
                        'step_order'      => 1,
                        'action_type'     => ViolationEscalationRule::ACTION_VERBAL_WARNING,
                        'deduction_type'  => ViolationEscalationRule::DEDUCTION_NONE,
                        'deduction_value' => 0,
                        'suspension_days' => 0,
                        'validity_months' => 3,
                        'notes'           => 'المرة الأولى: إنذار شفهي يسقط بالتقادم بعد 3 أشهر.',
                    ],
                    [
                        'step_order'      => 2,
                        'action_type'     => ViolationEscalationRule::ACTION_WRITTEN_WARNING,
                        'deduction_type'  => ViolationEscalationRule::DEDUCTION_NONE,
                        'deduction_value' => 0,
                        'suspension_days' => 0,
                        'validity_months' => 6,
                        'notes'           => 'المرة الثانية خلال فترة السريان: إنذار كتابي رسمي.',
                    ],
                    [
                        'step_order'      => 3,
                        'action_type'     => ViolationEscalationRule::ACTION_SALARY_DEDUCTION,
                        'deduction_type'  => ViolationEscalationRule::DEDUCTION_DAYS,
                        'deduction_value' => 0.5000,
                        'suspension_days' => 0,
                        'validity_months' => 6,
                        'notes'           => 'المرة الثالثة: خصم نصف يوم من الأجر اليومي.',
                    ],
                    [
                        'step_order'      => 4,
                        'action_type'     => ViolationEscalationRule::ACTION_SALARY_DEDUCTION,
                        'deduction_type'  => ViolationEscalationRule::DEDUCTION_DAYS,
                        'deduction_value' => 1.0000,
                        'suspension_days' => 0,
                        'validity_months' => 12,
                        'notes'           => 'المرة الرابعة: خصم أجر يوم كامل.',
                    ],
                    [
                        'step_order'      => 5,
                        'action_type'     => ViolationEscalationRule::ACTION_SALARY_DEDUCTION,
                        'deduction_type'  => ViolationEscalationRule::DEDUCTION_DAYS,
                        'deduction_value' => 2.0000,
                        'suspension_days' => 0,
                        'validity_months' => 12,
                        'notes'           => 'المرة الخامسة: خصم أجر يومين.',
                    ],
                    [
                        'step_order'      => 6,
                        'action_type'     => ViolationEscalationRule::ACTION_SUSPENSION,
                        'deduction_type'  => ViolationEscalationRule::DEDUCTION_DAYS,
                        'deduction_value' => 3.0000,
                        'suspension_days' => 3,
                        'validity_months' => 12,
                        'notes'           => 'المرة السادسة: إيقاف مؤقت عن العمل لمدة 3 أيام بدون أجر.',
                    ],
                    [
                        'step_order'      => 7,
                        'action_type'     => ViolationEscalationRule::ACTION_TERMINATION,
                        'deduction_type'  => ViolationEscalationRule::DEDUCTION_NONE,
                        'deduction_value' => 0,
                        'suspension_days' => 0,
                        'validity_months' => 12,
                        'notes'           => 'المرة السابعة: رفع توصية بإنهاء الخدمة لاعتماد مدير الموارد البشرية.',
                    ],
                ],
            ],

            // =========================================================================
            // 2. قالب بديل للتأخير التراكمي الشهري (مُعطّل افتراضياً لمنع ازدواجية الجزاء)
            // =========================================================================
            [
                'code'                  => 'CUMULATIVE_MONTHLY_LATE',
                'name'                  => 'تراكم دقائق التأخير خلال الفترة المالية',
                'description'           => 'قالب بديل للشركات التي تحاسب على مجموع دقائق التأخير الشهرية (مثلاً: تجاوز 120 دقيقة في الشهر) بدلاً من المحاسبة اليومية.',
                'detection_mode'        => ViolationType::DETECTION_ATTENDANCE_LATE,
                'trigger_mode'          => ViolationType::TRIGGER_CUMULATIVE_MONTHLY,
                'threshold_minutes'     => 120,
                'max_threshold_minutes' => null,
                'requires_approval'     => true,
                'is_active'             => false, // يُفعّل يدوياً إذا اختارت الشركة النظام التراكمي
                'steps'                 => [
                    [
                        'step_order'      => 1,
                        'action_type'     => ViolationEscalationRule::ACTION_WRITTEN_WARNING,
                        'deduction_type'  => ViolationEscalationRule::DEDUCTION_NONE,
                        'deduction_value' => 0,
                        'suspension_days' => 0,
                        'validity_months' => 3,
                        'notes'           => 'تجاوز 120 دقيقة تأخير شهرياً لأول مرة: إنذار كتابي.',
                    ],
                    [
                        'step_order'      => 2,
                        'action_type'     => ViolationEscalationRule::ACTION_SALARY_DEDUCTION,
                        'deduction_type'  => ViolationEscalationRule::DEDUCTION_DAYS,
                        'deduction_value' => 1.0000,
                        'suspension_days' => 0,
                        'validity_months' => 6,
                        'notes'           => 'تكرار تجاوز الحد الشهري: خصم يوم كامل.',
                    ],
                    [
                        'step_order'      => 3,
                        'action_type'     => ViolationEscalationRule::ACTION_SALARY_DEDUCTION,
                        'deduction_type'  => ViolationEscalationRule::DEDUCTION_DAYS,
                        'deduction_value' => 2.0000,
                        'suspension_days' => 0,
                        'validity_months' => 12,
                        'notes'           => 'المرة الثالثة: خصم يومين.',
                    ],
                ],
            ],

            // =========================================================================
            // 3. مخالفة آلية: الانصراف المبكر بدون إذن
            // =========================================================================
            [
                'code'                  => 'EARLY_LEAVE',
                'name'                  => 'الانصراف المبكر قبل نهاية الوردية بدون إذن',
                'description'           => 'يُفعّل آلياً عند تسجيل بصمة خروج مبكرة تتجاوز الحد المسموح به دون وجود إذن خروج معتمد.',
                'detection_mode'        => ViolationType::DETECTION_ATTENDANCE_EARLY_LEAVE,
                'trigger_mode'          => ViolationType::TRIGGER_PER_INCIDENT,
                'threshold_minutes'     => 15,
                'max_threshold_minutes' => null,
                'requires_approval'     => true,
                'is_active'             => true,
                'steps'                 => [
                    [
                        'step_order'      => 1,
                        'action_type'     => ViolationEscalationRule::ACTION_VERBAL_WARNING,
                        'deduction_type'  => ViolationEscalationRule::DEDUCTION_NONE,
                        'deduction_value' => 0,
                        'suspension_days' => 0,
                        'validity_months' => 3,
                        'notes'           => 'إنذار شفهي يسقط بعد 3 أشهر.',
                    ],
                    [
                        'step_order'      => 2,
                        'action_type'     => ViolationEscalationRule::ACTION_WRITTEN_WARNING,
                        'deduction_type'  => ViolationEscalationRule::DEDUCTION_NONE,
                        'deduction_value' => 0,
                        'suspension_days' => 0,
                        'validity_months' => 6,
                        'notes'           => 'إنذار كتابي.',
                    ],
                    [
                        'step_order'      => 3,
                        'action_type'     => ViolationEscalationRule::ACTION_SALARY_DEDUCTION,
                        'deduction_type'  => ViolationEscalationRule::DEDUCTION_DAYS,
                        'deduction_value' => 0.5000,
                        'suspension_days' => 0,
                        'validity_months' => 6,
                        'notes'           => 'خصم نصف يوم.',
                    ],
                    [
                        'step_order'      => 4,
                        'action_type'     => ViolationEscalationRule::ACTION_SALARY_DEDUCTION,
                        'deduction_type'  => ViolationEscalationRule::DEDUCTION_DAYS,
                        'deduction_value' => 1.0000,
                        'suspension_days' => 0,
                        'validity_months' => 12,
                        'notes'           => 'خصم يوم كامل.',
                    ],
                ],
            ],

            // =========================================================================
            // 4. مخالفة آلية: الغياب بدون إذن أو عذر مقبول
            // =========================================================================
            [
                'code'                  => 'UNEXCUSED_ABSENCE',
                'name'                  => 'الغياب عن العمل بدون إذن مسبق',
                'description'           => 'يُفعّل آلياً عند تسجيل حالة غياب للموظف في يوم عمل رسمي دون وجود إجازة معتمدة.',
                'detection_mode'        => ViolationType::DETECTION_ATTENDANCE_ABSENT,
                'trigger_mode'          => ViolationType::TRIGGER_PER_INCIDENT,
                'threshold_minutes'     => 0,
                'max_threshold_minutes' => null,
                'requires_approval'     => true,
                'is_active'             => true,
                'steps'                 => [
                    [
                        'step_order'      => 1,
                        'action_type'     => ViolationEscalationRule::ACTION_WRITTEN_WARNING,
                        'deduction_type'  => ViolationEscalationRule::DEDUCTION_DAYS,
                        'deduction_value' => 1.0000,
                        'suspension_days' => 0,
                        'validity_months' => 6,
                        'notes'           => 'إنذار كتابي مع خصم أجر يوم الغياب.',
                    ],
                    [
                        'step_order'      => 2,
                        'action_type'     => ViolationEscalationRule::ACTION_SALARY_DEDUCTION,
                        'deduction_type'  => ViolationEscalationRule::DEDUCTION_DAYS,
                        'deduction_value' => 2.0000,
                        'suspension_days' => 0,
                        'validity_months' => 12,
                        'notes'           => 'خصم يومين.',
                    ],
                    [
                        'step_order'      => 3,
                        'action_type'     => ViolationEscalationRule::ACTION_SUSPENSION,
                        'deduction_type'  => ViolationEscalationRule::DEDUCTION_DAYS,
                        'deduction_value' => 3.0000,
                        'suspension_days' => 3,
                        'validity_months' => 12,
                        'notes'           => 'إيقاف مؤقت 3 أيام بدون أجر.',
                    ],
                    [
                        'step_order'      => 4,
                        'action_type'     => ViolationEscalationRule::ACTION_TERMINATION,
                        'deduction_type'  => ViolationEscalationRule::DEDUCTION_NONE,
                        'deduction_value' => 0,
                        'suspension_days' => 0,
                        'validity_months' => 12,
                        'notes'           => 'توصية بإنهاء الخدمة لتكرار الغياب بدون عذر.',
                    ],
                ],
            ],

            // =========================================================================
            // 5. مخالفة إدارية / يدوية: عدم التواجد في المكان المخصص للعمل
            // =========================================================================
            [
                'code'                  => 'POST_ABANDONMENT',
                'name'                  => 'عدم التواجد في موقع العمل المخصص أثناء الدوام',
                'description'           => 'مخالفة لائحية يرفعها المشرف المباشر عند مغادرة الموظف لموقع عمله المحدد دون إذن رسمي.',
                'detection_mode'        => ViolationType::DETECTION_MANUAL,
                'trigger_mode'          => ViolationType::TRIGGER_PER_INCIDENT,
                'threshold_minutes'     => 0,
                'max_threshold_minutes' => null,
                'requires_approval'     => true,
                'is_active'             => true,
                'steps'                 => [
                    [
                        'step_order'      => 1,
                        'action_type'     => ViolationEscalationRule::ACTION_VERBAL_WARNING,
                        'deduction_type'  => ViolationEscalationRule::DEDUCTION_NONE,
                        'deduction_value' => 0,
                        'suspension_days' => 0,
                        'validity_months' => 3,
                        'notes'           => 'المرة الأولى: إنذار شفهي يسقط بعد 3 أشهر.',
                    ],
                    [
                        'step_order'      => 2,
                        'action_type'     => ViolationEscalationRule::ACTION_WRITTEN_WARNING,
                        'deduction_type'  => ViolationEscalationRule::DEDUCTION_NONE,
                        'deduction_value' => 0,
                        'suspension_days' => 0,
                        'validity_months' => 6,
                        'notes'           => 'المرة الثانية: إنذار كتابي.',
                    ],
                    [
                        'step_order'      => 3,
                        'action_type'     => ViolationEscalationRule::ACTION_SALARY_DEDUCTION,
                        'deduction_type'  => ViolationEscalationRule::DEDUCTION_DAYS,
                        'deduction_value' => 0.5000,
                        'suspension_days' => 0,
                        'validity_months' => 6,
                        'notes'           => 'المرة الثالثة: خصم نصف يوم.',
                    ],
                    [
                        'step_order'      => 4,
                        'action_type'     => ViolationEscalationRule::ACTION_SALARY_DEDUCTION,
                        'deduction_type'  => ViolationEscalationRule::DEDUCTION_DAYS,
                        'deduction_value' => 1.0000,
                        'suspension_days' => 0,
                        'validity_months' => 12,
                        'notes'           => 'المرة الرابعة: خصم يوم كامل.',
                    ],
                    [
                        'step_order'      => 5,
                        'action_type'     => ViolationEscalationRule::ACTION_SUSPENSION,
                        'deduction_type'  => ViolationEscalationRule::DEDUCTION_DAYS,
                        'deduction_value' => 2.0000,
                        'suspension_days' => 2,
                        'validity_months' => 12,
                        'notes'           => 'المرة الخامسة: إيقاف مؤقت يومين بدون أجر.',
                    ],
                    [
                        'step_order'      => 6,
                        'action_type'     => ViolationEscalationRule::ACTION_TERMINATION,
                        'deduction_type'  => ViolationEscalationRule::DEDUCTION_NONE,
                        'deduction_value' => 0,
                        'suspension_days' => 0,
                        'validity_months' => 12,
                        'notes'           => 'المرة السادسة: توصية بإنهاء الخدمة.',
                    ],
                ],
            ],

            // =========================================================================
            // 6. مخالفة إدارية / يدوية: مخالفة تعليمات الأمن والسلامة المهنية (HSE)
            // =========================================================================
            [
                'code'                  => 'SAFETY_VIOLATION',
                'name'                  => 'مخالفة تعليمات الأمن والسلامة المهنية',
                'description'           => 'عدم الالتزام بمعدات الوقاية أو تعليمات السلامة المعتمدة في بيئة العمل.',
                'detection_mode'        => ViolationType::DETECTION_MANUAL,
                'trigger_mode'          => ViolationType::TRIGGER_PER_INCIDENT,
                'threshold_minutes'     => 0,
                'max_threshold_minutes' => null,
                'requires_approval'     => true,
                'is_active'             => true,
                'steps'                 => [
                    [
                        'step_order'      => 1,
                        'action_type'     => ViolationEscalationRule::ACTION_WRITTEN_WARNING,
                        'deduction_type'  => ViolationEscalationRule::DEDUCTION_NONE,
                        'deduction_value' => 0,
                        'suspension_days' => 0,
                        'validity_months' => 6,
                        'notes'           => 'تبدأ بإنذار كتابي مباشر نظراً لحساسية السلامة المهنية.',
                    ],
                    [
                        'step_order'      => 2,
                        'action_type'     => ViolationEscalationRule::ACTION_SALARY_DEDUCTION,
                        'deduction_type'  => ViolationEscalationRule::DEDUCTION_DAYS,
                        'deduction_value' => 1.0000,
                        'suspension_days' => 0,
                        'validity_months' => 12,
                        'notes'           => 'خصم يوم كامل.',
                    ],
                    [
                        'step_order'      => 3,
                        'action_type'     => ViolationEscalationRule::ACTION_SUSPENSION,
                        'deduction_type'  => ViolationEscalationRule::DEDUCTION_DAYS,
                        'deduction_value' => 3.0000,
                        'suspension_days' => 3,
                        'validity_months' => 12,
                        'notes'           => 'إيقاف مؤقت 3 أيام بدون أجر.',
                    ],
                    [
                        'step_order'      => 4,
                        'action_type'     => ViolationEscalationRule::ACTION_TERMINATION,
                        'deduction_type'  => ViolationEscalationRule::DEDUCTION_NONE,
                        'deduction_value' => 0,
                        'suspension_days' => 0,
                        'validity_months' => 12,
                        'notes'           => 'توصية بإنهاء الخدمة لتعريض المنشأة أو العاملين للخطر.',
                    ],
                ],
            ],
        ];

        DB::transaction(function () use ($violationTemplates): void {
            foreach ($violationTemplates as $template) {
                $steps = $template['steps'];
                unset($template['steps']);

                $violationType = ViolationType::updateOrCreate(
                    ['code' => $template['code']],
                    $template
                );

                $stepOrders = [];
                foreach ($steps as $stepData) {
                    $stepOrders[] = $stepData['step_order'];

                    ViolationEscalationRule::updateOrCreate(
                        [
                            'violation_type_id' => $violationType->id,
                            'step_order'        => $stepData['step_order'],
                        ],
                        $stepData
                    );
                }

                // حذف أي درجات زائدة إذا تم تقليص السلم عند إعادة تشغيل التهيئة
                ViolationEscalationRule::where('violation_type_id', $violationType->id)
                    ->whereNotIn('step_order', $stepOrders)
                    ->delete();
            }
        });

        $this->command?->info('تمت تهيئة قوالب اللوائح والمخالفات وسلالم التدرج العقابي بنجاح.');
    }
}