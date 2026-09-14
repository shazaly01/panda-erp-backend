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
        Schema::table('purchasing_bills', function (Blueprint $table): void {
            $table->foreignId('warehouse_id')
                ->nullable()
                ->after('supplier_id')
                ->constrained('inventory_warehouses')
                ->nullOnDelete();
        });
    }

    /**
     * إلغاء الهجرة.
     */
    public function down(): void
    {
        Schema::table('purchasing_bills', function (Blueprint $table): void {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn('warehouse_id');
        });
    }
};