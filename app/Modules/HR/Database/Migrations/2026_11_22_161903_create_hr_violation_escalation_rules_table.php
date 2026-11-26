<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_violation_escalation_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('violation_type_id')
                ->constrained('hr_violation_types')
                ->cascadeOnDelete();

            // ترتيب الدرجة في السلم (1: شفهي، 2: كتابي، 3: خصم نصف يوم...)
            $table->unsignedSmallInteger('step_order');

            // نوع الإجراء: verbal_warning | written_warning | salary_deduction | suspension | termination
            $table->string('action_type', 40);

            // آلية الخصم المالي: none | days (أيام من الأجر اليومي) | fixed (مبلغ مقطوع) | percentage (نسبة من الأساسي)
            $table->string('deduction_type', 20)->default('none');
            $table->decimal('deduction_value', 10, 4)->default(0); // مثال: 0.5000 نصف يوم، 1.0000 يوم، 2.0000 يومين

            // عدد أيام الإيقاف المؤقت (عند اختيار action_type = suspension)
            $table->unsignedSmallInteger('suspension_days')->default(0);

            // مدة سريان الجزاء بالأشهر قبل سقوطه بالتقادم وإعادة التعيين (مثال: 3 أشهر للشفهي)
            $table->unsignedSmallInteger('validity_months')->default(3);

            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->unique(['violation_type_id', 'step_order'], 'hr_violation_step_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_violation_escalation_rules');
    }
};