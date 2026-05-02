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
        Schema::create('hr_working_schedule_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('working_schedule_id')->constrained('hr_working_schedules')->cascadeOnDelete();

            $table->integer('day_number')->comment('The specific day in the cycle (e.g., 1 to 28)');

            // الوردية المربوطة. إذا كان الحقل null فهذا يعني أن هذا اليوم هو يوم راحة (Off Day)
            $table->foreignId('shift_id')->nullable()->constrained('hr_shifts')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // منع تكرار نفس اليوم داخل نفس القالب
            $table->unique(['working_schedule_id', 'day_number'], 'unique_schedule_day');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_working_schedule_lines');
    }
};
