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
        Schema::create('hr_calendar_exceptions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('مثال: طوارئ الحرب، عطلة عيد الفطر');
            $table->string('type')->default('public_holiday')->comment('public_holiday, emergency, etc.');
            $table->date('start_date');
            $table->date('end_date')->nullable()->comment('إذا كان فارغاً يعني أن الحالة مستمرة');
            $table->boolean('treat_as_overtime_if_worked')
                  ->default(true)
                  ->comment('إذا كان مفعلاً، أي عمل في هذه الفترة يُحسب كإضافي بالكامل');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_calendar_exceptions');
    }
};
