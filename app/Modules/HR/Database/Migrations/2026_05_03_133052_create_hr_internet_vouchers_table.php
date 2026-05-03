<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('hr_internet_vouchers', function (Blueprint $table) {
            // المعرفات باستخدام DECIMAL(18, 0)
            $table->decimal('id', 18, 0)->primary();

            $table->string('code')->unique();
            $table->string('capacity')->default('1GB');
            $table->enum('status', ['available', 'assigned'])->default('available');

            // مفاتيح الربط مع الموظف وسجل الحضور
            $table->decimal('employee_id', 18, 0)->nullable();
            $table->decimal('attendance_log_id', 18, 0)->nullable();

            $table->timestamp('assigned_at')->nullable();
            $table->date('expires_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // الفهارس لتسريع استعلامات الـ 10,000 كود
            $table->index(['status', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_internet_vouchers');
    }
};
