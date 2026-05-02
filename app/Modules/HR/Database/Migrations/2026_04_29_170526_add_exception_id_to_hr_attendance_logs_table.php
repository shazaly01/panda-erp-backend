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
        Schema::table('hr_attendance_logs', function (Blueprint $table) {
            $table->foreignId('calendar_exception_id')
                  ->nullable()
                  ->after('shift_id')
                  ->constrained('hr_calendar_exceptions')
                  ->nullOnDelete()
                  ->comment('الاستثناء الذي تم احتساب اليوم بناءً عليه (مثل الطوارئ أو العطلة)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hr_attendance_logs', function (Blueprint $table) {
            $table->dropForeign(['calendar_exception_id']);
            $table->dropColumn('calendar_exception_id');
        });
    }
};
