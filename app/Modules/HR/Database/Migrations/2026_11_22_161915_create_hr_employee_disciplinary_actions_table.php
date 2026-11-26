<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_employee_disciplinary_actions', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 50)->unique(); // رقم مرجعي تسلسلي عبر SequenceService

            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('violation_type_id')->constrained('hr_violation_types');
            $table->foreignId('escalation_rule_id')
                ->nullable()
                ->constrained('hr_violation_escalation_rules')
                ->nullOnDelete();

            // مراجع الربط الآلي لمنع تكرار إصدار الجزاء لنفس البصمة أو نفس الفترة
            $table->unsignedBigInteger('attendance_log_id')->nullable()->index();
            $table->unsignedBigInteger('pay_period_id')->nullable()->index();

            // لقطة ثابتة (Snapshot) للجزاء وقت وقوعه حتى لا تتأثر السجلات التاريخية بتعديل اللائحة مستقبلاً
            $table->unsignedSmallInteger('step_order');
            $table->string('action_type', 40);
            $table->decimal('deduction_days', 8, 2)->default(0);
            $table->decimal('deduction_amount', 15, 2)->default(0);
            $table->unsignedSmallInteger('suspension_days')->default(0);

            $table->date('violation_date')->index();
            $table->date('expires_at')->nullable()->index(); // تاريخ سقوط الجزاء بالتقادم

            // حالة الجزاء: pending (بانتظار الاعتماد) | approved (معتمد وسارٍ) | cancelled (ملغى)
            $table->string('status', 20)->default('pending')->index();
            $table->text('reason')->nullable();

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 255)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_employee_disciplinary_actions');
    }
};