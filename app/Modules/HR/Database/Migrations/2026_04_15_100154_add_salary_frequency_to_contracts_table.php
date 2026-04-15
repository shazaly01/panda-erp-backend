<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            // إضافة الحقل كـ String مع قيمة افتراضية "monthly" لتجنب تعطل العقود القديمة
            $table->string('salary_frequency')->default('monthly')->after('salary_structure_id');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('salary_frequency');
        });
    }
};
