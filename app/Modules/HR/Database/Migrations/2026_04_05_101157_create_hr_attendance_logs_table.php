<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('shift_id')->nullable()->constrained('hr_shifts');

            $table->date('date'); // تاريخ اليوم
            $table->time('check_in')->nullable(); // وقت الحضور الفعلي
            $table->time('check_out')->nullable(); // وقت الانصراف الفعلي

            $table->integer('delay_minutes')->default(0); // دقائق التأخير المحسوبة آلياً
            $table->integer('early_leave_minutes')->default(0); // دقائق الانصراف المبكر
            $table->integer('overtime_minutes')->default(0); // دقائق العمل الإضافي

            $table->enum('status', ['present', 'absent', 'late', 'on_leave'])->default('present');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_attendance_logs');
    }
};
