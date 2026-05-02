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
        Schema::table('contracts', function (Blueprint $table) {
            $table->foreignId('working_schedule_id')
                  ->nullable()
                  ->after('pay_group_id')
                  ->constrained('hr_working_schedules')
                  ->nullOnDelete();

            $table->date('schedule_start_date')
                  ->nullable()
                  ->after('working_schedule_id')
                  ->comment('التاريخ المرجعي لبدء حساب الدورة للورديات المتغيرة');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hr_contracts', function (Blueprint $table) {
            $table->dropForeign(['working_schedule_id']);
            $table->dropColumn(['working_schedule_id', 'schedule_start_date']);
        });
    }
};
