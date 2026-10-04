<?php

declare(strict_types=1);

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
        Schema::table('hr_overtime_policies', function (Blueprint $table) {
            $table->string('overtime_source', 30)
                ->default('punch')
                ->after('hours_to_day_threshold')
                ->comment('punch: تلقائي من البصمة, supervisor: اعتماد المشرف');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hr_overtime_policies', function (Blueprint $table) {
            $table->dropColumn('overtime_source');
        });
    }
};