<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            // حذف الحقل القديم
            $table->dropColumn('salary_frequency');

            // إضافة الربط بمجموعة الدفع
            $table->foreignId('pay_group_id')->nullable()->after('overtime_policy_id')->constrained('hr_pay_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['pay_group_id']);
            $table->dropColumn('pay_group_id');
            $table->string('salary_frequency')->default('monthly')->after('overtime_policy_id');
        });
    }
};
