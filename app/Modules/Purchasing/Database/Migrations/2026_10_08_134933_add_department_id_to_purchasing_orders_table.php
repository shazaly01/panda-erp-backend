<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تشغيل الهجرة.
     */
    public function up(): void
    {
        Schema::table('purchasing_orders', function (Blueprint $table): void {
            $table->foreignId('department_id')
                ->nullable()
                ->after('requisition_id')
                ->constrained('departments')
                ->nullOnDelete();

            $table->index('department_id');
        });
    }

    /**
     * إلغاء الهجرة.
     */
    public function down(): void
    {
        Schema::table('purchasing_orders', function (Blueprint $table): void {
            $table->dropForeign(['department_id']);
            $table->dropIndex(['department_id']);
            $table->dropColumn('department_id');
        });
    }
};