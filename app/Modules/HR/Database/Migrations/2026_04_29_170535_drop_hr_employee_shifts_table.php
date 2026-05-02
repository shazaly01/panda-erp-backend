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
        // حذف الجدول القديم نهائياً لأننا انتقلنا لمعمارية القوالب
        Schema::dropIfExists('hr_employee_shifts');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // في حال أردنا التراجع (Rollback)، نقوم بإعادة بناء الجدول القديم
        Schema::create('hr_employee_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('hr_shifts')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->json('weekend_days')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
