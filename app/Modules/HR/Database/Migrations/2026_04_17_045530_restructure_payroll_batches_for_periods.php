<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_batches', function (Blueprint $table) {
            // حذف التواريخ القديمة
            $table->dropColumn(['start_date', 'end_date']);

            // إضافة الفترة ونوع المسير
            $table->foreignId('pay_period_id')->nullable()->after('name')->constrained('hr_pay_periods')->nullOnDelete();
            $table->string('run_type')->default('regular')->after('pay_period_id'); // regular, overtime_only
        });
    }

    public function down(): void
    {
        Schema::table('payroll_batches', function (Blueprint $table) {
            $table->dropForeign(['pay_period_id']);
            $table->dropColumn(['pay_period_id', 'run_type']);
            $table->date('start_date')->nullable()->after('name');
            $table->date('end_date')->nullable()->after('start_date');
        });
    }
};
