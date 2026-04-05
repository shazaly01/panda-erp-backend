<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_employee_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('shift_id')->constrained('hr_shifts');

            $table->date('start_date'); // بداية تطبيق الوردية على الموظف
            $table->date('end_date')->nullable(); // نهايتها (إذا كانت الوردية مؤقتة)

            // تحديد أيام الراحة الأسبوعية (مثال: الجمعة والسبت)
            $table->json('weekend_days')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_employee_shifts');
    }
};
