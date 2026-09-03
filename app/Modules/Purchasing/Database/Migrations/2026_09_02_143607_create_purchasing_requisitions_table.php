<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تشغيل الهجرة.
     */
    public function up(): void
    {
        Schema::create('purchasing_requisitions', function (Blueprint $table): void {
            $table->id();
            $table->string('requisition_number', 50)->unique(); // متوافق مع تسلسل pur_requisition
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->date('request_date');
            $table->date('required_date')->nullable();
            $table->string('priority', 20)->default('medium'); // low, medium, high, urgent
            $table->string('status', 30)->default('draft'); // draft, pending_approval, approved, rejected, ordered, cancelled
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            
            // بيانات الاعتماد
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            // التدقيق والتتبع
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'request_date']);
            $table->index('priority');
        });
    }

    /**
     * إلغاء الهجرة.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchasing_requisitions');
    }
};