<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_violation_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name'); // مثال: التأخير الصباحي، عدم التواجد في الموقع المخصص
            $table->string('code', 50)->unique(); // مثال: LATE_ARRIVAL, POST_ABANDONMENT
            $table->text('description')->nullable();

            // طريقة الرصد: manual (يدوي/إداري) | attendance_late | attendance_early_leave | attendance_absent
            $table->string('detection_mode', 40)->default('manual')->index();

            // آلية التفعيل لمخالفات البصمة: per_incident (لكل حادثة) | cumulative_monthly (تراكمي خلال الفترة)
            $table->string('trigger_mode', 30)->default('per_incident');

            // الحد الأدنى والأقصى بالدقائق لتفعيل المخالفة الآلية (يدعم تقسيم التأخير البسيط والجسيم)
            $table->unsignedInteger('threshold_minutes')->default(0);
            $table->unsignedInteger('max_threshold_minutes')->nullable();

            // هل يتطلب الجزاء اعتماد المشرف/الـ HR قبل تنفيذه مالياً؟
            $table->boolean('requires_approval')->default(true);
            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_violation_types');
    }
};