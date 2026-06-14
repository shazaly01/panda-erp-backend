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
        Schema::table('hr_visitors', function (Blueprint $table) {
            // إضافة تاريخ الزيارة المتوقع بعد حقل سبب الزيارة
            $table->date('visit_date')->nullable()->after('purpose');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hr_visitors', function (Blueprint $table) {
            $table->dropColumn('visit_date');
        });
    }
};
