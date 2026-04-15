<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->foreignId('overtime_policy_id')
                  ->nullable()
                  ->after('salary_structure_id')
                  ->constrained('hr_overtime_policies')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['overtime_policy_id']);
            $table->dropColumn('overtime_policy_id');
        });
    }
};
