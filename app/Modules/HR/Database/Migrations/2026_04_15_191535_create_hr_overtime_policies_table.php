<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_overtime_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // اسم السياسة (مثال: سياسة الإدارة، سياسة العمال)
            $table->integer('working_days_per_month')->default(30);
            $table->integer('working_hours_per_day')->default(8);
            $table->decimal('regular_rate', 8, 2)->default(1.5); // معدل الأيام العادية
            $table->decimal('weekend_rate', 8, 2)->default(2.0); // معدل أيام الراحة
            $table->decimal('holiday_rate', 8, 2)->default(2.0); // معدل العطلات الرسمية
            $table->boolean('is_daily_basis')->default(false);   // هل يعامل كيوم كامل؟
            $table->integer('hours_to_day_threshold')->default(8); // عدد الساعات ليحسب يوم
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_overtime_policies');
    }
};
